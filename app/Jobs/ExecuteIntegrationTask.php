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
            $records = $parser->parse($resolved['path'], [
                'delimiter' => $task->delimiter,
                'has_header' => $task->has_header,
                'record_path' => Arr::get($task->options, 'record_path'),
            ]);

            $totalRecords = $records->count();
            $runService->addLog($run, "Znaleziono {$totalRecords} rekordów");

            if ($totalRecords === 0) {
                $runService->finish($run, 0, 0);
                return;
            }

            $chunkSize = 50;
            $chunkJobs = [];
            $productCatalogId = $task->catalog_id;

            // Process records in chunks
            foreach ($records->chunk($chunkSize) as $chunkIndex => $chunk) {
                if ($chunk->isEmpty()) {
                    continue;
                }

                $chunkJobs[] = new ProcessIntegrationImportChunk(
                    $run->id,
                    $chunk->toArray(),
                    $productMappings,
                    $categoryMappings,
                    $productCatalogId,
                );
            }

            if (empty($chunkJobs)) {
                $runService->finish($run, 0, 0);
                return;
            }

            $runService->markQueued($run, count($chunkJobs));

            foreach ($chunkJobs as $job) {
                dispatch($job);
            }

            $scheduler->scheduleNext($task);
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
