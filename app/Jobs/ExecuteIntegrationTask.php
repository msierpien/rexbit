<?php

namespace App\Jobs;

use App\Jobs\ProcessIntegrationImportChunk;
use App\Models\IntegrationTask;
use App\Services\Integrations\Import\ImportParserFactory;
use App\Services\Integrations\Import\ImportSchedulerService;
use App\Services\Integrations\Import\ImportSourceResolver;
use App\Services\Integrations\Tasks\TaskRunService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Throwable;

class ExecuteIntegrationTask implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $taskId)
    {
    }

    public function handle(
        ImportParserFactory $parserFactory,
        ImportSourceResolver $sourceResolver,
        TaskRunService $runService,
        ImportSchedulerService $scheduler,
    ): void {
        $task = IntegrationTask::with(['integration.user'])->find($this->taskId);

        if (! $task || ! $task->integration || ! $task->integration->user) {
            return;
        }

        $run = $runService->start($task);

        try {
            $resolved = $sourceResolver->resolve($task);
            $parser = $parserFactory->make($task->format);

            // Extract mappings from JSON format
            $productMappings = collect($task->mappings ?? [])
                ->where('target_type', 'product')
                ->pluck('source_field', 'target_field')
                ->toArray();
            
            $categoryMappings = collect($task->mappings ?? [])
                ->where('target_type', 'category')
                ->pluck('source_field', 'target_field')
                ->toArray();

            $runService->addLog($run, "Rozpoczęto import z {$task->source_location}");
            $runService->addLog($run, "Mappings produktów: " . count($productMappings));
            $runService->addLog($run, "Mappings kategorii: " . count($categoryMappings));

            // Parse file and create import chunks
            $records = $parser->iterate($resolved['path'], [
                'delimiter' => $task->delimiter,
                'has_header' => $task->has_header,
                'record_path' => Arr::get($task->options, 'record_path'),
            ]);

            $chunkSize = 50;
            $chunkJobs = [];
            $productCatalogId = $task->catalog_id;
            $currentChunk = [];
            $totalRecords = 0;

            // Process records in chunks directly from generator
            foreach ($records as $record) {
                $totalRecords++;
                $currentChunk[] = $record;

                if (count($currentChunk) >= $chunkSize) {
                    $chunkJobs[] = new ProcessIntegrationImportChunk(
                        $run->id,
                        $currentChunk,
                        $productMappings,
                        $categoryMappings,
                        $productCatalogId,
                    );
                    $currentChunk = [];
                }
            }

            // Add remaining records as final chunk
            if (!empty($currentChunk)) {
                $chunkJobs[] = new ProcessIntegrationImportChunk(
                    $run->id,
                    $currentChunk,
                    $productMappings,
                    $categoryMappings,
                    $productCatalogId,
                );
            }

            $runService->addLog($run, "Znaleziono {$totalRecords} rekordów");

            if ($totalRecords === 0) {
                $runService->finish($run, 0, 0);
                return;
            }

            if (empty($chunkJobs)) {
                $runService->finish($run, 0, 0);
                return;
            }

            $runService->markQueued($run, count($chunkJobs));

            foreach ($chunkJobs as $job) {
                dispatch($job)->onQueue('import');
            }

            $scheduler->updateNextRun($task);
        } catch (Throwable $exception) {
            $runService->fail($run, $exception->getMessage());
            throw $exception;
        } finally {
            if (($resolved['temporary'] ?? false) && isset($resolved['path'])) {
                @unlink($resolved['path']);
            }
        }
    }
}
