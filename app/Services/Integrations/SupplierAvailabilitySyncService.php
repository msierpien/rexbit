<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use App\Models\IntegrationProductLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierAvailabilitySyncService
{
    public function __construct(
        protected PrestashopProductService $prestashop,
    ) {
    }

    public function syncToPrestashop(Integration $integration, ?int $contractorId = null, array $options = []): array
    {
        $stats = [
            'total' => 0,
            'synced' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $limit = $options['limit'] ?? null;
        $processed = 0;

        // Find PrestaShop links that have corresponding supplier availability data
        // We need to join with supplier links to get availability data
        $query = IntegrationProductLink::query()
            ->select('integration_product_links.*')
            ->where('integration_product_links.integration_id', $integration->id)
            ->whereNotNull('integration_product_links.external_product_id')
            ->join('integration_product_links as supplier_links', function ($join) {
                $join->on('integration_product_links.product_id', '=', 'supplier_links.product_id')
                     ->whereNotNull('supplier_links.supplier_availability');
            });

        if ($contractorId) {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                $query->whereRaw("(supplier_links.supplier_availability->>'contractor_id')::bigint = ?", [$contractorId]);
            } else {
                $query->where('supplier_links.supplier_availability->contractor_id', (string) $contractorId);
            }
        }

        $query->orderBy('integration_product_links.id')->chunk(100, function ($links) use (&$stats, &$processed, $limit, $integration) {
            foreach ($links as $prestashopLink) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                $processed++;
                $stats['total']++;

                // Get the supplier availability data from the supplier link
                $supplierLink = IntegrationProductLink::where('product_id', $prestashopLink->product_id)
                    ->whereNotNull('supplier_availability')
                    ->first();

                if (!$supplierLink) {
                    $stats['failed']++;
                    $stats['errors'][] = [
                        'product_id' => $prestashopLink->product_id,
                        'external_product_id' => $prestashopLink->external_product_id,
                        'message' => 'No supplier availability data found',
                    ];
                    continue;
                }

                $result = $this->prestashop->updateProductAvailability(
                    $integration,
                    (string) $prestashopLink->external_product_id,
                    $supplierLink->getPrestashopOutOfStockValue(),
                    $supplierLink->getPrestashopAvailableLater()
                );

                if ($result['success'] ?? false) {
                    $stats['synced']++;
                    $this->markLinkSynced($prestashopLink);
                } else {
                    $stats['failed']++;
                    $error = $result['error'] ?? 'Unknown error';
                    $stats['errors'][] = [
                        'product_id' => $prestashopLink->product_id,
                        'external_product_id' => $prestashopLink->external_product_id,
                        'message' => $error,
                    ];

                    $this->markLinkFailed($prestashopLink, $error);
                }
            }
        });

        return $stats;
    }

    protected function markLinkSynced(IntegrationProductLink $link): void
    {
        $metadata = $link->metadata ?? [];
        $metadata['supplier_sync'] = [
            'last_synced_at' => now()->toIso8601String(),
            'last_status' => 'success',
        ];

        $link->forceFill(['metadata' => $metadata])->save();

        Log::info('Supplier availability synced', [
            'integration_id' => $link->integration_id,
            'product_id' => $link->product_id,
            'external_product_id' => $link->external_product_id,
        ]);
    }

    protected function markLinkFailed(IntegrationProductLink $link, string $message): void
    {
        $metadata = $link->metadata ?? [];
        $metadata['supplier_sync'] = [
            'last_synced_at' => now()->toIso8601String(),
            'last_status' => 'failed',
            'last_error' => $message,
        ];

        $link->forceFill(['metadata' => $metadata])->save();

        Log::warning('Supplier availability sync failed', [
            'integration_id' => $link->integration_id,
            'product_id' => $link->product_id,
            'external_product_id' => $link->external_product_id,
            'error' => $message,
        ]);
    }
}
