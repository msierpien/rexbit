<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Services\Integrations\IntegrationInventorySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncIntegrationInventory implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected int $integrationId;

    /**
     * @var array<int>
     */
    protected array $productIds;

    public function __construct(Integration $integration, array $productIds = [])
    {
        $this->integrationId = $integration->id;
        $this->productIds = array_values(array_unique(array_map('intval', $productIds)));
        $this->queue = 'integrations';
    }

    public function handle(IntegrationInventorySyncService $service): void
    {
        $integration = Integration::query()
            ->whereKey($this->integrationId)
            ->first();

        if (! $integration) {
            return;
        }

        $service->syncIntegration($integration, $this->productIds);
    }

    public function tags(): array
    {
        return [
            'integration:' . $this->integrationId,
        ];
    }
}
