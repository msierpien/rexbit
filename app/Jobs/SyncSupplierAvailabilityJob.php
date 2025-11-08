<?php

namespace App\Jobs;

use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Models\IntegrationTask;
use App\Services\Integrations\PrestashopDatabaseSyncService;
use App\Services\Integrations\SupplierAvailabilitySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSupplierAvailabilityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $integrationId,
    ) {
        $this->onQueue('integrations');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $integration = Integration::find($this->integrationId);

        if (!$integration) {
            Log::error("Integration not found for supplier availability sync", [
                'integration_id' => $this->integrationId,
            ]);
            return;
        }

        Log::info("Starting supplier availability sync", [
            'integration_id' => $integration->id,
            'integration_name' => $integration->name,
            'integration_type' => $integration->type->value,
        ]);

        try {
            if ($integration->type === IntegrationType::PRESTASHOP_DB) {
                // Use database sync service
                $this->syncUsingDatabase($integration);
            } else {
                // Use API sync service
                $this->syncUsingApi($integration);
            }

            Log::info("Supplier availability sync completed", [
                'integration_id' => $integration->id,
            ]);
        } catch (\Exception $e) {
            Log::error("Supplier availability sync failed", [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function syncUsingDatabase(Integration $integration): void
    {
        // Create temporary task for database sync
        $task = IntegrationTask::create([
            'integration_id' => $integration->id,
            'resource_type' => 'supplier-availability',
            'name' => 'Manual supplier availability sync',
            'format' => 'database',
            'source_type' => 'manual',
            'source_location' => 'manual-sync',
            'task_type' => 'sync',
        ]);

        try {
            $syncService = new PrestashopDatabaseSyncService($task, app(\App\Integrations\IntegrationService::class));
            $stats = $syncService->syncToPrestashop();

            Log::info("Database sync completed", [
                'integration_id' => $integration->id,
                'task_id' => $task->id,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error("Database sync failed", [
                'integration_id' => $integration->id,
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function syncUsingApi(Integration $integration): void
    {
        $syncService = app(SupplierAvailabilitySyncService::class);
        $syncService->syncForIntegration($integration);
    }
}
