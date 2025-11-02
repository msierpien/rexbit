<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationType;
use App\Jobs\SyncIntegrationInventory;
use App\Models\Integration;
use App\Models\User;
use App\Models\WarehouseStockTotal;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class IntegrationInventorySyncService
{
    public function __construct(
        protected PrestashopProductService $prestashop,
    ) {
    }

    public function dispatchForUser(User $user, array $productIds = []): void
    {
        $user->integrations()
            ->where('type', IntegrationType::PRESTASHOP->value)
            ->get()
            ->each(fn (Integration $integration) => $this->dispatchForIntegration($integration, $productIds));
    }

    public function dispatchForIntegration(Integration $integration, array $productIds = []): void
    {
        if ($this->getSyncMode($integration) === 'disabled') {
            return;
        }

        if (! empty($productIds)) {
            foreach (array_chunk($productIds, 200) as $chunk) {
                SyncIntegrationInventory::dispatch($integration, $chunk)->afterCommit();
            }

            return;
        }

        SyncIntegrationInventory::dispatch($integration)->afterCommit();
    }

    public function shouldRunScheduled(Integration $integration): bool
    {
        if ($this->getSyncMode($integration) === 'disabled') {
            return false;
        }

        $interval = $this->getSyncIntervalMinutes($integration);
        $lastRun = Arr::get($integration->meta ?? [], 'inventory_sync.last_run_at');

        if (! $lastRun) {
            return true;
        }

        return Carbon::parse($lastRun)->addMinutes($interval)->isPast();
    }

    public function syncIntegration(Integration $integration, array $productIds = []): array
    {
        $mode = $this->getSyncMode($integration);

        if ($mode === 'disabled') {
            return ['synced' => 0];
        }

        $linksQuery = $integration->productLinks()->whereNotNull('external_product_id');

        if (! empty($productIds)) {
            $linksQuery->whereIn('product_id', $productIds);
        }

        $synced = 0;
        $now = Carbon::now();

        $linksQuery->chunkById(100, function (Collection $links) use ($integration, $mode, &$synced, $now): void {
            if ($mode === 'local_to_presta') {
                $synced += $this->syncLocalToPrestashop($integration, $links, $now);
            } elseif ($mode === 'prestashop_to_local') {
                $synced += $this->syncPrestashopToLocal($integration, $links, $now);
            }
        });

        $this->updateIntegrationMeta($integration, $mode, $now, $synced);

        return ['synced' => $synced];
    }

    protected function syncLocalToPrestashop(Integration $integration, Collection $links, Carbon $timestamp): int
    {
        $warehouseId = Arr::get($integration->config, 'primary_warehouse_id');

        $productIds = $links->pluck('product_id')->filter()->unique()->values();

        $stockTotals = WarehouseStockTotal::query()
            ->whereIn('product_id', $productIds)
            ->when($warehouseId, fn ($query) => $query->where('warehouse_location_id', $warehouseId))
            ->selectRaw('product_id, SUM(on_hand - reserved) as available_quantity')
            ->groupBy('product_id')
            ->pluck('available_quantity', 'product_id');

        foreach ($links as $link) {
            $available = (float) ($stockTotals[$link->product_id] ?? 0);

            $metadata = $link->metadata ?? [];
            $metadata['inventory'] = array_merge($metadata['inventory'] ?? [], [
                'last_local_quantity' => $available,
                'last_local_sync_at' => $timestamp->toIso8601String(),
            ]);

            $link->fill([
                'metadata' => $metadata,
            ])->save();

            // TODO: Call Prestashop API to update stock availability.
        }

        return $links->count();
    }

    protected function syncPrestashopToLocal(Integration $integration, Collection $links, Carbon $timestamp): int
    {
        $productIds = $links->pluck('external_product_id')->filter()->unique()->values();
        $externalCache = [];

        foreach ($links as $link) {
            $externalId = (string) $link->external_product_id;

            if ($externalId === '') {
                continue;
            }

            if (! array_key_exists($externalId, $externalCache)) {
                $externalCache[$externalId] = $this->prestashop->fetchProductStock($integration, $externalId);
            }

            $remoteQuantity = $externalCache[$externalId];

            if ($remoteQuantity === null) {
                continue;
            }

            $metadata = $link->metadata ?? [];
            $metadata['inventory'] = array_merge($metadata['inventory'] ?? [], [
                'last_remote_quantity' => $remoteQuantity,
                'last_remote_sync_at' => $timestamp->toIso8601String(),
            ]);

            $link->fill([
                'metadata' => $metadata,
            ])->save();

            // TODO: Update local warehouse stock totals with $remoteQuantity if desired.
        }

        return $links->count();
    }

    protected function updateIntegrationMeta(Integration $integration, string $mode, Carbon $timestamp, int $synced): void
    {
        $meta = $integration->meta ?? [];
        $meta['inventory_sync'] = array_merge($meta['inventory_sync'] ?? [], [
            'last_run_at' => $timestamp->toIso8601String(),
            'last_mode' => $mode,
            'last_synced_count' => $synced,
        ]);

        $integration->meta = $meta;
        $integration->save();
    }

    protected function getSyncMode(Integration $integration): string
    {
        return Arr::get($integration->config ?? [], 'inventory_sync_mode', 'disabled') ?? 'disabled';
    }

    protected function getSyncIntervalMinutes(Integration $integration): int
    {
        return max(5, (int) Arr::get($integration->config ?? [], 'inventory_sync_interval_minutes', 180));
    }
}
