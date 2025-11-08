<?php

namespace App\Services\Integrations;

use App\Models\IntegrationProductLink;
use App\Models\IntegrationTask;
use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SupplierAvailabilityImportService
{
    public function processRow(IntegrationTask $task, array $row, array $mappings, array $options = []): array
    {
        $payload = $this->mapRow($row, $mappings);

        $availabilityOptions = Arr::get($options, 'supplier_availability', []);
        $matchBy = Arr::get($availabilityOptions, 'match_by', 'sku_or_ean');
        $missingBehavior = Arr::get($availabilityOptions, 'missing_behavior', 'skip');

        $sku = $payload['sku'] ?? $payload['supplier_sku'] ?? null;
        $ean = $payload['ean'] ?? null;

        $product = $this->findProduct($task, $sku, $ean, $matchBy);

        if (! $product) {
            if ($missingBehavior === 'skip') {
                return [
                    'action' => 'skipped',
                    'reason' => 'product_not_found',
                    'sku' => $sku,
                    'ean' => $ean,
                ];
            }

            throw new \RuntimeException(
                sprintf(
                    'Produkt nie został znaleziony dla SKU/EAN: %s / %s',
                    $sku ?: '—',
                    $ean ?: '—'
                )
            );
        }

        $link = IntegrationProductLink::firstOrCreate(
            [
                'integration_id' => $task->integration_id,
                'product_id' => $product->id,
            ],
            [
                'sku' => $sku ?: $product->sku,
                'ean' => $ean ?: $product->ean,
                'matched_by' => $sku ? 'sku' : 'ean',
                'is_manual' => false,
            ]
        );

        $contractorId = Arr::get($availabilityOptions, 'contractor_id');
        $defaultDeliveryDays = (int) Arr::get($availabilityOptions, 'default_delivery_days', 0);
        $syncPurchasePrice = (bool) Arr::get($availabilityOptions, 'sync_purchase_price', false);

        $stockQuantity = (int) round((float) ($payload['stock_quantity'] ?? 0));
        $deliveryDays = $this->resolveDeliveryDays($payload['delivery_days'] ?? null, $defaultDeliveryDays);
        $isAvailable = $this->resolveAvailabilityFlag($payload['is_available'] ?? null, $stockQuantity);
        $purchasePrice = $this->resolveDecimal(
            $payload['purchase_price'] ?? $payload['purchase_price_net'] ?? null
        );

        $statusChanged = $link->isAvailableAtSupplier() !== $isAvailable;

        $extra = array_filter([
            'supplier_sku' => $payload['supplier_sku'] ?? null,
            'supplier_code' => $payload['supplier_code'] ?? null,
            'available_later' => $payload['available_later'] ?? null,
            'purchase_price' => $purchasePrice,
        ], fn ($value) => $value !== null && $value !== '');

        $link->updateSupplierAvailability(
            $isAvailable,
            $stockQuantity,
            $deliveryDays,
            $contractorId,
            $extra
        );

        if ($syncPurchasePrice && $purchasePrice !== null && $purchasePrice > 0) {
            $product->forceFill([
                'purchase_price_net' => $purchasePrice,
            ])->save();
        }

        return [
            'action' => $link->wasRecentlyCreated ? 'created' : 'updated',
            'product_id' => $product->id,
            'link_id' => $link->id,
            'status_changed' => $statusChanged,
        ];
    }

    /**
     * @param  array<string, string>  $mappings
     * @return array<string, mixed>
     */
    protected function mapRow(array $row, array $mappings): array
    {
        $payload = [];

        foreach ($mappings as $target => $source) {
            if (! $source) {
                continue;
            }

            $payload[$target] = $row[$source] ?? null;
        }

        return $payload;
    }

    protected function findProduct(IntegrationTask $task, ?string $sku, ?string $ean, string $strategy = 'sku_or_ean'): ?Product
    {
        $userId = $task->integration?->user_id;

        if (! $userId) {
            return null;
        }

        $strategy = in_array($strategy, ['sku', 'ean', 'sku_or_ean'], true) ? $strategy : 'sku_or_ean';

        if ($strategy === 'sku' && $sku) {
            return Product::query()
                ->where('user_id', $userId)
                ->where('sku', $sku)
                ->first();
        }

        if ($strategy === 'ean' && $ean) {
            return Product::query()
                ->where('user_id', $userId)
                ->where('ean', $ean)
                ->first();
        }

        if ($strategy === 'sku_or_ean') {
            if ($sku) {
                $product = Product::query()
                    ->where('user_id', $userId)
                    ->where('sku', $sku)
                    ->first();

                if ($product) {
                    return $product;
                }
            }

            if ($ean) {
                return Product::query()
                    ->where('user_id', $userId)
                    ->where('ean', $ean)
                    ->first();
            }
        }

        return null;
    }

    protected function resolveDeliveryDays(mixed $value, int $default): int
    {
        if ($value === null || $value === '') {
            return max(0, $default);
        }

        return max(0, (int) round((float) $value));
    }

    protected function resolveAvailabilityFlag(mixed $value, int $stockQuantity): bool
    {
        if ($value === null || $value === '') {
            return $stockQuantity > 0;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        $normalized = Str::lower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'tak', 'y'], true);
    }

    protected function resolveDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $normalized = (float) str_replace(',', '.', (string) $value);

        return round($normalized, 2);
    }
}
