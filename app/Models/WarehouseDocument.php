<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'warehouse_location_id',
        'contractor_id',
        'type',
        'number',
        'issued_at',
        'metadata',
        'status',
    ];

    protected $casts = [
        'issued_at' => 'date',
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

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseDocumentItem::class);
    }
}
