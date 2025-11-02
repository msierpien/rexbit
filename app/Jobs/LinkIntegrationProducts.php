<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Services\Integrations\IntegrationProductLinkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class LinkIntegrationProducts implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected int $integrationId;

    protected int $userId;

    /**
     * @var array<int>
     */
    protected array $productIds;

    /**
     * Create a new job instance.
     *
     * @param  array<int>  $productIds
     */
    public function __construct(Integration $integration, array $productIds)
    {
        $this->integrationId = $integration->id;
        $this->userId = $integration->user_id;
        $this->productIds = array_values(array_unique(array_map('intval', $productIds)));

        $this->queue = 'integrations';
    }

    /**
     * Execute the job.
     */
    public function handle(IntegrationProductLinkService $service): void
    {
        $integration = Integration::with('user')
            ->whereKey($this->integrationId)
            ->first();

        if (! $integration || ! $integration->user) {
            return;
        }

        $productIds = Collection::make($this->productIds)
            ->filter()
            ->values()
            ->all();

        if (empty($productIds)) {
            return;
        }

        $service->autoLink($integration, $productIds);
    }

    public function tags(): array
    {
        return [
            'integration:'.$this->integrationId,
            'user:'.$this->userId,
        ];
    }
}
