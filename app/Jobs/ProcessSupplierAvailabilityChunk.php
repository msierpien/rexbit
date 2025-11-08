<?php

namespace App\Jobs;

use App\Models\IntegrationTaskRun;
use App\Notifications\IntegrationImportFinished;
use App\Services\Integrations\Import\ImportSchedulerService;
use App\Services\Integrations\Tasks\TaskRunService;
use App\Services\Integrations\SupplierAvailabilityImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessSupplierAvailabilityChunk implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $runId,
        public array $rows,
        public array $mappings,
        public array $options = []
    ) {
    }

    public function handle(
        TaskRunService $taskRunService,
        SupplierAvailabilityImportService $importService,
        ImportSchedulerService $scheduler
    ): void {
        $run = IntegrationTaskRun::with(['task.integration.user'])->find($this->runId);

        if (! $run || ! $run->task) {
            return;
        }

        $processed = 0;
        $success = 0;
        $failure = 0;
        $samples = [];
        $errors = [];

        foreach ($this->rows as $row) {
            $processed++;

            try {
                $result = $importService->processRow($run->task, $row, $this->mappings, $this->options);

                if (($result['action'] ?? null) === 'skipped') {
                    $failure++;

                    if (count($errors) < 5) {
                        $errors[] = sprintf(
                            'Pominięto wiersz (produkt nie znaleziony) SKU: %s, EAN: %s',
                            $result['sku'] ?? '—',
                            $result['ean'] ?? '—'
                        );
                    }

                    continue;
                }

                $success++;

                if (count($samples) < 5) {
                    $samples[] = [
                        'product_id' => $result['product_id'] ?? null,
                        'link_id' => $result['link_id'] ?? null,
                        'action' => $result['action'] ?? null,
                        'status_changed' => $result['status_changed'] ?? false,
                    ];
                }
            } catch (Throwable $exception) {
                $failure++;

                if (count($errors) < 5) {
                    $errors[] = $exception->getMessage();
                }
            }
        }

        $updatedRun = $taskRunService->applyChunkResult(
            $run,
            $processed,
            $success,
            $failure,
            $samples,
            $errors
        );

        $this->finalizeIfCompleted($updatedRun, $scheduler);
    }

    public function failed(Throwable $exception): void
    {
        $taskRunService = app(TaskRunService::class);
        $run = IntegrationTaskRun::with(['task.integration.user'])->find($this->runId);

        if (! $run) {
            return;
        }

        $taskRunService->fail($run, $exception->getMessage());

        $user = $run->task?->integration?->user;

        if ($user) {
            $user->notify(new IntegrationImportFinished($run, false, $exception->getMessage()));
        }
    }

    protected function finalizeIfCompleted(IntegrationTaskRun $run, ImportSchedulerService $scheduler): void
    {
        if (($run->meta['pending_chunks'] ?? 0) !== 0) {
            return;
        }

        $task = $run->task;
        $user = $task?->integration?->user;

        if ($run->status === 'completed') {
            $message = $run->message ?? __('Import dostępności zakończony.');
            $run->forceFill(['message' => $message])->save();

            if ($task) {
                $task->forceFill(['last_fetched_at' => now()])->save();
                $scheduler->updateNextRun($task);
            }

            if ($user) {
                $user->notify(new IntegrationImportFinished($run));
            }
        } elseif ($run->status === 'failed' && $user) {
            $user->notify(new IntegrationImportFinished($run, false, $run->message));
        }
    }
}
