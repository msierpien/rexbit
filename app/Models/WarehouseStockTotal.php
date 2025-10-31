<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStockTotal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'warehouse_location_id',
        'on_hand',
        'reserved',
        'incoming',
    ];

    protected $casts = [
        'on_hand' => 'decimal:3',
        'reserved' => 'decimal:3',
        'incoming' => 'decimal:3',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }
}
