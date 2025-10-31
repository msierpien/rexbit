<?php

namespace App\Services\Integrations\Import;

use App\Models\IntegrationImportProfile;
use App\Models\IntegrationImportRun;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ImportRunService
{
    public function start(IntegrationImportProfile $profile): IntegrationImportRun
    {
        $run = new IntegrationImportRun([
            'status' => 'running',
            'started_at' => now(),
            'meta' => [
                'samples' => [],
                'errors' => [],
                'pending_chunks' => 0,
            ],
        ]);

        $profile->runs()->save($run);

        return $run->refresh();
    }

    public function markQueued(IntegrationImportRun $run, int $chunks): IntegrationImportRun
    {
        $meta = $run->meta ?? [];
        $meta['samples'] = $meta['samples'] ?? [];
        $meta['errors'] = $meta['errors'] ?? [];
        $meta['pending_chunks'] = $chunks;

        $run->forceFill([
            'meta' => $meta,
        ])->save();

        return $run->refresh();
    }

    public function completeEmptyRun(IntegrationImportRun $run, ?string $message = null): IntegrationImportRun
    {
        $run->forceFill([
            'status' => 'completed',
            'processed_count' => 0,
            'success_count' => 0,
            'failure_count' => 0,
            'finished_at' => now(),
            'message' => $message,
            'meta' => array_replace($run->meta ?? [], [
                'samples' => [],
                'errors' => [],
                'pending_chunks' => 0,
            ]),
        ])->save();

        return $run->refresh();
    }

    public function applyChunkResult(
        int $runId,
        int $processed,
        int $success,
        int $failure,
        array $samples = [],
        array $errors = []
    ): IntegrationImportRun {
        return DB::transaction(function () use ($runId, $processed, $success, $failure, $samples, $errors) {
            $run = IntegrationImportRun::query()->lockForUpdate()->findOrFail($runId);

            $run->processed_count += $processed;
            $run->success_count += $success;
            $run->failure_count += $failure;

            $meta = $run->meta ?? [];
            $meta['samples'] = array_slice(array_merge(Arr::get($meta, 'samples', []), $samples), 0, 5);
            $meta['errors'] = array_slice(array_merge(Arr::get($meta, 'errors', []), $errors), 0, 5);

            if (isset($meta['pending_chunks'])) {
                $meta['pending_chunks'] = max(0, (int) $meta['pending_chunks'] - 1);
                if ($meta['pending_chunks'] === 0 && $run->status !== 'failed') {
                    $run->status = 'completed';
                    $run->finished_at = now();
                }
            }

            $run->meta = $meta;
            $run->save();

            return $run->refresh();
        });
    }

    public function complete(IntegrationImportRun $run, int $processed, int $success, int $failed, ?string $message = null, array $meta = []): IntegrationImportRun
    {
        $run->forceFill([
            'status' => 'completed',
            'processed_count' => $processed,
            'success_count' => $success,
            'failure_count' => $failed,
            'finished_at' => now(),
            'message' => $message,
            'meta' => $meta,
        ])->save();

        return $run->refresh();
    }

    public function fail(IntegrationImportRun $run, string $message, array $meta = []): IntegrationImportRun
    {
        $meta = array_replace($run->meta ?? [], $meta, ['pending_chunks' => 0]);

        $run->forceFill([
            'status' => 'failed',
            'finished_at' => now(),
            'message' => $message,
            'meta' => $meta,
        ])->save();

        return $run->refresh();
    }
}
