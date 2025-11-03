<?php

namespace App\Models;

use App\Enums\InventoryCountStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryCount extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'user_id',
        'warehouse_location_id',
        'name',
        'description',
        'started_at',
        'completed_at',
        'status',
        'counted_by',
        'approved_by',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'status' => InventoryCountStatus::class,
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryCountItem::class);
    }

    /**
     * Get items with discrepancies (difference between system and counted quantity)
     */
    public function itemsWithDiscrepancies(): HasMany
    {
        return $this->hasMany(InventoryCountItem::class)
            ->whereRaw('counted_quantity != system_quantity');
    }

    /**
     * Calculate total number of products counted
     */
    public function getTotalProductsCountedAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Calculate total discrepancies count
     */
    public function getTotalDiscrepanciesAttribute(): int
    {
        return $this->itemsWithDiscrepancies()->count();
    }

    /**
     * Calculate total value of discrepancies
     */
    public function getTotalDiscrepancyValueAttribute(): float
    {
        return $this->itemsWithDiscrepancies()
            ->selectRaw('SUM((counted_quantity - system_quantity) * unit_cost) as total')
            ->value('total') ?? 0;
    }

    /**
     * Check if inventory count can be completed
     */
    public function canBeCompleted(): bool
    {
        return $this->status === InventoryCountStatus::IN_PROGRESS && 
               $this->items()->count() > 0;
    }

    /**
     * Check if inventory count can be approved
     */
    public function canBeApproved(): bool
    {
        return $this->status === InventoryCountStatus::COMPLETED;
    }

    /**
     * Generate adjustment document for discrepancies
     */
    public function generateAdjustmentDocument(): ?WarehouseDocument
    {
        if (!$this->canBeApproved() || $this->totalDiscrepancies === 0) {
            return null;
        }

        // This will be implemented in the service layer
        return null;
    }
}