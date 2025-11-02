<?php

namespace App\Jobs;

use App\Jobs\ProcessIntegrationImportChunk;
use App\Models\IntegrationImportProfile;
use App\Notifications\IntegrationImportFinished;
use App\Services\Integrations\Import\ImportParserFactory;
use App\Services\Integrations\Import\ImportRunService;
use App\Services\Integrations\Import\ImportSchedulerService;
use App\Services\Integrations\Import\ImportSourceResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Throwable;

class ExecuteIntegrationImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $profileId)
    {
    }

    public function handle(
        ImportParserFactory $parserFactory,
        ImportSourceResolver $sourceResolver,
        ImportRunService $runService,
        ImportSchedulerService $scheduler,
    ): void {
        $profile = IntegrationImportProfile::with(['integration.user', 'mappings'])->find($this->profileId);

        if (! $profile || ! $profile->integration || ! $profile->integration->user) {
            return;
        }

        $run = $runService->start($profile);

        try {
            $resolved = $sourceResolver->resolve($profile);
            $parser = $parserFactory->make($profile->format);

            $productMappings = $profile->mappings
                ->where('target_type', 'product')
                ->pluck('source_field', 'target_field')
                ->toArray();
            $categoryMappings = $profile->mappings
                ->where('target_type', 'category')
                ->pluck('source_field', 'target_field')
                ->toArray();

            $chunkSize = config('integrations.import.chunk_size', 200);
            $currentChunk = [];
            $chunkJobs = [];

            foreach ($parser->iterate($resolved['path'], [
                'delimiter' => $profile->delimiter,
                'has_header' => $profile->has_header,
                'record_path' => Arr::get($profile->options, 'record_path'),
            ]) as $row) {
                $currentChunk[] = $row;

                if (count($currentChunk) >= $chunkSize) {
                    $chunkJobs[] = new ProcessIntegrationImportChunk(
                        $run->id,
                        $currentChunk,
                        $productMappings,
                        $categoryMappings,
                        $profile->catalog_id
                    );
                    $currentChunk = [];
                }
            }

            if (! empty($currentChunk)) {
                $chunkJobs[] = new ProcessIntegrationImportChunk(
                    $run->id,
                    $currentChunk,
                    $productMappings,
                    $categoryMappings,
                    $profile->catalog_id
                );
            }

            if ($resolved['temporary'] ?? false) {
                @unlink($resolved['path']);
            }

            if (empty($chunkJobs)) {
                $runService->completeEmptyRun($run, 'Brak rekordów do zaimportowania.');
                $scheduler->updateNextRun($profile);

                return;
            }

            $runService->markQueued($run, count($chunkJobs));

            foreach ($chunkJobs as $job) {
                dispatch($job);
            }
        } catch (Throwable $exception) {
            if (isset($resolved) && ($resolved['temporary'] ?? false) && isset($resolved['path']) && file_exists($resolved['path'])) {
                @unlink($resolved['path']);
            }

            $run = $runService->fail($run, $exception->getMessage());

            $profile->integration->user?->notify(new \App\Notifications\IntegrationImportFinished($run, false, $exception->getMessage()));

            throw $exception;
        }
    }
}
