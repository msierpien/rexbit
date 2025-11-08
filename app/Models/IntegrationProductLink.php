<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

class IntegrationProductLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_id',
        'product_id',
        'catalog_id',
        'external_product_id',
        'sku',
        'ean',
        'matched_by',
        'is_manual',
        'metadata',
        'supplier_availability',
    ];

    protected $casts = [
        'is_manual' => 'boolean',
        'metadata' => 'array',
        'supplier_availability' => 'array',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ProductCatalog::class, 'catalog_id');
    }

    public function isAvailableAtSupplier(): bool
    {
        return (bool) Arr::get($this->supplier_availability ?? [], 'is_available', false);
    }

    public function getSupplierStockQuantity(): int
    {
        return (int) Arr::get($this->supplier_availability ?? [], 'stock_quantity', 0);
    }

    public function getSupplierDeliveryDays(): int
    {
        return max(0, (int) Arr::get($this->supplier_availability ?? [], 'delivery_days', 0));
    }

    public function getPrestashopOutOfStockValue(): int
    {
        return $this->isAvailableAtSupplier() ? 1 : 0;
    }

    public function getPrestashopAvailableLater(?string $fallback = null): string
    {
        $availability = $this->supplier_availability ?? [];

        if (!empty($availability['available_later'])) {
            return (string) $availability['available_later'];
        }

        if ($this->isAvailableAtSupplier()) {
            $days = $this->getSupplierDeliveryDays();
            return $days > 0
                ? __('Wysyłka za :days dni', ['days' => $days])
                : __('Dostępny u dostawcy');
        }

        return $fallback ?? __('Produkt niedostępny');
    }

    public function updateSupplierAvailability(
        bool $isAvailable,
        int $stockQuantity,
        ?int $deliveryDays = null,
        ?int $contractorId = null,
        array $extra = []
    ): self {
        $current = $this->supplier_availability ?? [];
        $now = now()->toIso8601String();
        $statusChanged = Arr::get($current, 'is_available') !== $isAvailable;

        $payload = array_merge($current, $extra);
        $payload['is_available'] = $isAvailable;
        $payload['stock_quantity'] = $stockQuantity;

        if ($deliveryDays !== null) {
            $payload['delivery_days'] = max(0, $deliveryDays);
        } elseif (!isset($payload['delivery_days'])) {
            $payload['delivery_days'] = 0;
        }

        if ($contractorId !== null) {
            $payload['contractor_id'] = $contractorId;
        }

        $payload['last_checked_at'] = $now;

        if ($statusChanged) {
            $payload['last_status_change_at'] = $now;
        } elseif (!isset($payload['last_status_change_at'])) {
            $payload['last_status_change_at'] = $now;
        }

        $this->forceFill([
            'supplier_availability' => $payload,
        ])->save();

        return $this->refresh();
    }
}
