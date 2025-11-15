<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id', 
        'integration_product_link_id',
        'warehouse_location_id',
        'name',
        'sku',
        'ean',
        'external_product_id',
        'product_reference',
        'quantity',
        'price_net',
        'price_gross',
        'unit_price_net',
        'unit_price_gross',
        'vat_rate',
        'discount_total',
        'weight',
        'quantity_reserved',
        'quantity_shipped',
        'prestashop_data',
        'metadata'
    ];

    protected $casts = [
        'prestashop_data' => 'array',
        'metadata' => 'array',
        'price_net' => 'decimal:4',
        'price_gross' => 'decimal:4',
        'unit_price_net' => 'decimal:4',
        'unit_price_gross' => 'decimal:4',
        'vat_rate' => 'decimal:2',
        'discount_total' => 'decimal:4',
        'weight' => 'decimal:3'
    ];

    // 🔐 SECURITY: Dziedziczenie bezpieczeństwa z Order
    protected static function booted(): void
    {
        static::addGlobalScope('user_order_items', function (Builder $builder) {
            if (auth()->check()) {
                $builder->whereHas('order', function ($q) {
                    $q->where('user_id', auth()->id());
                });
            }
        });
    }

    // Relacje
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function integrationProductLink(): BelongsTo
    {
        return $this->belongsTo(IntegrationProductLink::class);
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }

    // Metody pomocnicze
    public function getTotalPriceAttribute(): float
    {
        return $this->quantity * $this->unit_price_gross - $this->discount_total;
    }

    public function getQuantityToShipAttribute(): int
    {
        return $this->quantity - $this->quantity_shipped;
    }

    public function getQuantityToReserveAttribute(): int
    {
        return $this->quantity - $this->quantity_reserved;
    }
}
