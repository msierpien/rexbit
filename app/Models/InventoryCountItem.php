<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_count_id',
        'product_id',
        'system_quantity',
        'counted_quantity',
        'unit_cost',
        'notes',
        'counted_at',
        'scanned_ean',
    ];

    protected $casts = [
        'system_quantity' => 'decimal:3',
        'counted_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'counted_at' => 'datetime',
    ];

    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the quantity difference (counted - system)
     */
    public function getQuantityDifferenceAttribute(): float
    {
        return $this->counted_quantity - $this->system_quantity;
    }

    /**
     * Get the value difference (quantity difference * unit cost)
     */
    public function getValueDifferenceAttribute(): float
    {
        return $this->quantity_difference * $this->unit_cost;
    }

    /**
     * Check if this item has a discrepancy
     */
    public function hasDiscrepancy(): bool
    {
        return $this->system_quantity != $this->counted_quantity;
    }

    /**
     * Get discrepancy type: surplus, shortage, or match
     */
    public function getDiscrepancyTypeAttribute(): string
    {
        if ($this->counted_quantity > $this->system_quantity) {
            return 'surplus'; // nadwyżka
        } elseif ($this->counted_quantity < $this->system_quantity) {
            return 'shortage'; // niedobór
        }
        return 'match'; // zgodność
    }
}