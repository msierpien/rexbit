<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationType;
use App\Jobs\SyncIntegrationInventory;
use App\Models\Integration;
use App\Models\IntegrationSyncLog;
use App\Models\IntegrationSyncLogItem;
use App\Models\User;
use App\Models\WarehouseStockTotal;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service for synchronizing inventory between local warehouse and external integrations.
 * 
 * Supports two-way synchronization:
 * - local_to_presta: Sync local warehouse stock to Prestashop
 * - prestashop_to_local: Sync Prestashop stock to local warehouse (metadata only)
 * 
 * Synchronization is triggered:
 * - Automatically when warehouse documents are posted
 * - On schedule (every 5 minutes by default, configurable per integration)
 * - Manually via command: php artisan integrations:sync-inventory
 */
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

        $totalLinks = $linksQuery->count();
        
        // Create sync log
        $syncLog = IntegrationSyncLog::create([
            'integration_id' => $integration->id,
            'user_id' => auth()->id(),
            'type' => 'inventory',
            'direction' => $mode,
            'status' => 'pending',
            'total_items' => $totalLinks,
        ]);

        $syncLog->markAsRunning();

        $synced = 0;
        $now = Carbon::now();

        try {
            $linksQuery->chunkById(100, function (Collection $links) use ($integration, $mode, &$synced, $now, $syncLog): void {
                if ($mode === 'local_to_presta') {
                    $synced += $this->syncLocalToPrestashop($integration, $links, $now, $syncLog);
                } elseif ($mode === 'prestashop_to_local') {
                    $synced += $this->syncPrestashopToLocal($integration, $links, $now, $syncLog);
                }
            });

            $syncLog->markAsCompleted();
            
            Log::info('Integration inventory sync completed', [
                'integration_id' => $integration->id,
                'sync_log_id' => $syncLog->id,
                'mode' => $mode,
                'total' => $totalLinks,
                'success' => $syncLog->success_count,
                'failed' => $syncLog->failed_count,
            ]);
        } catch (\Exception $e) {
            $syncLog->markAsFailed($e->getMessage());
            
            Log::error('Integration inventory sync failed', [
                'integration_id' => $integration->id,
                'sync_log_id' => $syncLog->id,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }

        $this->updateIntegrationMeta($integration, $mode, $now, $synced);

        return ['synced' => $synced, 'sync_log_id' => $syncLog->id];
    }

    protected function syncLocalToPrestashop(Integration $integration, Collection $links, Carbon $timestamp, IntegrationSyncLog $syncLog): int
    {
        $warehouseId = Arr::get($integration->config, 'primary_warehouse_id');

        $productIds = $links->pluck('product_id')->filter()->unique()->values();

        $stockTotals = WarehouseStockTotal::query()
            ->whereIn('product_id', $productIds)
            ->when($warehouseId, fn ($query) => $query->where('warehouse_location_id', $warehouseId))
            ->selectRaw('product_id, SUM(on_hand - reserved) as available_quantity')
            ->groupBy('product_id')
            ->pluck('available_quantity', 'product_id');

        $synced = 0;

        // Use batch updates for better performance when syncing many products
        if ($links->count() > 10) {
            return $this->syncLocalToPrestashopBatch($integration, $links, $stockTotals, $timestamp, $syncLog);
        }

        // Individual updates for small batches
        foreach ($links as $link) {
            $available = (float) ($stockTotals[$link->product_id] ?? 0);

            $metadata = $link->metadata ?? [];
            $metadata['inventory'] = array_merge($metadata['inventory'] ?? [], [
                'last_local_quantity' => $available,
                'last_local_sync_at' => $timestamp->toIso8601String(),
            ]);

            // Update stock in Prestashop
            try {
                Log::info('Syncing stock to Prestashop', [
                    'integration_id' => $integration->id,
                    'product_id' => $link->product_id,
                    'external_product_id' => $link->external_product_id,
                    'quantity' => $available,
                ]);

                $result = $this->prestashop->updateProductStock(
                    $integration,
                    (string) $link->external_product_id,
                    $available
                );

                if ($result['success']) {
                    $metadata['inventory']['last_sync_status'] = 'success';
                    $metadata['inventory']['last_sync_error'] = null;
                    $synced++;
                    $syncLog->incrementSuccess();
                    
                    IntegrationSyncLogItem::create([
                        'sync_log_id' => $syncLog->id,
                        'product_id' => $link->product_id,
                        'external_id' => $link->external_product_id,
                        'status' => 'success',
                        'quantity' => $available,
                    ]);

                    Log::info('Stock synced successfully', [
                        'integration_id' => $integration->id,
                        'product_id' => $link->product_id,
                        'external_product_id' => $link->external_product_id,
                        'quantity' => $available,
                    ]);
                } else {
                    $metadata['inventory']['last_sync_status'] = 'failed';
                    $metadata['inventory']['last_sync_error'] = $result['error'] ?? 'Unknown error';
                    $syncLog->incrementFailed();
                    
                    IntegrationSyncLogItem::create([
                        'sync_log_id' => $syncLog->id,
                        'product_id' => $link->product_id,
                        'external_id' => $link->external_product_id,
                        'status' => 'failed',
                        'quantity' => $available,
                        'error_message' => $result['error'] ?? 'Unknown error',
                    ]);
                    
                    Log::warning('Failed to sync stock to Prestashop', [
                        'integration_id' => $integration->id,
                        'link_id' => $link->id,
                        'product_id' => $link->product_id,
                        'external_product_id' => $link->external_product_id,
                        'error' => $result['error'] ?? 'Unknown error',
                    ]);
                }
            } catch (\Exception $e) {
                $metadata['inventory']['last_sync_status'] = 'error';
                $metadata['inventory']['last_sync_error'] = $e->getMessage();
                $syncLog->incrementFailed();
                
                IntegrationSyncLogItem::create([
                    'sync_log_id' => $syncLog->id,
                    'product_id' => $link->product_id,
                    'external_id' => $link->external_product_id,
                    'status' => 'failed',
                    'quantity' => $available,
                    'error_message' => $e->getMessage(),
                ]);
                
                Log::error('Exception during Prestashop stock sync', [
                    'integration_id' => $integration->id,
                    'link_id' => $link->id,
                    'product_id' => $link->product_id,
                    'external_product_id' => $link->external_product_id,
                    'exception' => $e->getMessage(),
                ]);
            }

            $link->fill([
                'metadata' => $metadata,
            ])->save();
        }

        return $synced;
    }

    /**
     * Sync local stocks to Prestashop using batch operations
     */
    protected function syncLocalToPrestashopBatch(Integration $integration, Collection $links, Collection $stockTotals, Carbon $timestamp, IntegrationSyncLog $syncLog): int
    {
        // Prepare batch updates
        $updates = [];
        foreach ($links as $link) {
            $available = (float) ($stockTotals[$link->product_id] ?? 0);
            $updates[(string) $link->external_product_id] = $available;
        }

        Log::info('Starting batch sync to Prestashop', [
            'integration_id' => $integration->id,
            'total_products' => count($updates),
        ]);

        // Execute batch update
        $result = $this->prestashop->updateProductStockBatch($integration, $updates);

        // Update metadata for all links
        $synced = 0;
        foreach ($links as $link) {
            $available = (float) ($stockTotals[$link->product_id] ?? 0);
            $externalId = (string) $link->external_product_id;

            $metadata = $link->metadata ?? [];
            $metadata['inventory'] = array_merge($metadata['inventory'] ?? [], [
                'last_local_quantity' => $available,
                'last_local_sync_at' => $timestamp->toIso8601String(),
            ]);

            // Check if this product succeeded or failed
            if (isset($result['errors'][$externalId])) {
                $metadata['inventory']['last_sync_status'] = 'failed';
                $metadata['inventory']['last_sync_error'] = $result['errors'][$externalId];
                $syncLog->incrementFailed();
                
                IntegrationSyncLogItem::create([
                    'sync_log_id' => $syncLog->id,
                    'product_id' => $link->product_id,
                    'external_id' => $link->external_product_id,
                    'status' => 'failed',
                    'quantity' => $available,
                    'error_message' => $result['errors'][$externalId],
                ]);
            } else {
                $metadata['inventory']['last_sync_status'] = 'success';
                $metadata['inventory']['last_sync_error'] = null;
                $synced++;
                $syncLog->incrementSuccess();
                
                IntegrationSyncLogItem::create([
                    'sync_log_id' => $syncLog->id,
                    'product_id' => $link->product_id,
                    'external_id' => $link->external_product_id,
                    'status' => 'success',
                    'quantity' => $available,
                ]);
            }

            $link->fill([
                'metadata' => $metadata,
            ])->save();
        }

        if ($result['failed'] > 0) {
            Log::warning('Batch sync to Prestashop completed with errors', [
                'integration_id' => $integration->id,
                'total' => $links->count(),
                'success' => $result['success'],
                'failed' => $result['failed'],
            ]);
        } else {
            Log::info('Batch sync to Prestashop completed successfully', [
                'integration_id' => $integration->id,
                'total' => $links->count(),
                'success' => $result['success'],
            ]);
        }

        return $synced;
    }

    protected function syncPrestashopToLocal(Integration $integration, Collection $links, Carbon $timestamp, IntegrationSyncLog $syncLog): int
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
                $syncLog->incrementSkipped();
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
            
            $syncLog->incrementSuccess();
            
            IntegrationSyncLogItem::create([
                'sync_log_id' => $syncLog->id,
                'product_id' => $link->product_id,
                'external_id' => $link->external_product_id,
                'status' => 'success',
                'quantity' => $remoteQuantity,
            ]);

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

    public function getSyncMode(Integration $integration): string
    {
        return Arr::get($integration->config ?? [], 'inventory_sync_mode', 'disabled') ?? 'disabled';
    }

    public function getSyncIntervalMinutes(Integration $integration): int
    {
        return max(5, (int) Arr::get($integration->config ?? [], 'inventory_sync_interval_minutes', 180));
    }
}
