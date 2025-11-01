<?php

namespace App\Services\Integrations\Tasks;

use App\Models\IntegrationTask;
use App\Models\IntegrationTaskRun;

class TaskRunService
{
    public function start(IntegrationTask $task): IntegrationTaskRun
    {
        $run = $task->runs()->create([
            'status' => 'running',
            'started_at' => now(),
            'processed_count' => 0,
            'success_count' => 0,
            'failure_count' => 0,
            'meta' => [],
        ]);

        return $run;
    }

    public function markQueued(IntegrationTaskRun $run, int $chunks): IntegrationTaskRun
    {
        $meta = $run->meta ?? [];
        $log = $meta['log'] ?? [];
        $log[] = [
            'timestamp' => now()->toISOString(),
            'message' => "Zaplanowano {$chunks} chunków do przetworzenia",
            'level' => 'info',
        ];
        $meta['log'] = $log;

        $run->forceFill([
            'meta' => $meta,
        ])->save();

        return $run->refresh();
    }

    public function finish(IntegrationTaskRun $run, int $imported, int $processed): IntegrationTaskRun
    {
        $meta = $run->meta ?? [];
        $log = $meta['log'] ?? [];
        $log[] = [
            'timestamp' => now()->toISOString(),
            'message' => "Import zakończony: {$imported}/{$processed} rekordów",
            'level' => 'success',
        ];
        $meta['log'] = $log;

        $run->forceFill([
            'status' => 'completed',
            'finished_at' => now(),
            'meta' => $meta,
        ])->save();

        return $run->refresh();
    }

    public function fail(IntegrationTaskRun $run, string $message): IntegrationTaskRun
    {
        $meta = $run->meta ?? [];
        $log = $meta['log'] ?? [];
        $log[] = [
            'timestamp' => now()->toISOString(),
            'message' => $message,
            'level' => 'error',
        ];
        $meta['log'] = $log;

        $run->forceFill([
            'status' => 'failed',
            'finished_at' => now(),
            'message' => $message,
            'meta' => $meta,
        ])->save();

        return $run->refresh();
    }

    /**
     * Add log entry to the run
     */
    public function addLog(IntegrationTaskRun $run, string $message): void
    {
        $meta = $run->meta ?? [];
        $currentLog = $meta['log'] ?? [];
        $currentLog[] = '[' . now()->format('Y-m-d H:i:s') . '] ' . $message;
        $meta['log'] = $currentLog;
        
        $run->update(['meta' => $meta]);
    }

    /**
     * Apply chunk processing results
     */
    public function applyChunkResult(
        IntegrationTaskRun $run,
        int $processed,
        int $success,
        int $failure,
        array $samples = [],
        array $errors = []
    ): IntegrationTaskRun {
        $meta = $run->meta ?? [];
        
        // Update counters
        $run->increment('processed_count', $processed);
        $run->increment('success_count', $success);
        $run->increment('failure_count', $failure);
        
        // Decrease pending chunks
        $pendingChunks = ($meta['pending_chunks'] ?? 1) - 1;
        $meta['pending_chunks'] = $pendingChunks;
        
        // Add samples and errors to meta
        if (!empty($samples)) {
            $existingSamples = $meta['samples'] ?? [];
            $meta['samples'] = array_merge($existingSamples, array_slice($samples, 0, 5 - count($existingSamples)));
        }
        
        if (!empty($errors)) {
            $existingErrors = $meta['errors'] ?? [];
            $meta['errors'] = array_merge($existingErrors, array_slice($errors, 0, 5 - count($existingErrors)));
        }
        
        $run->update(['meta' => $meta]);
        
        // If all chunks processed, mark as completed
        if ($pendingChunks <= 0) {
            $this->finish($run, $run->success_count, $run->failure_count);
        }
        
        return $run->fresh();
    }
}