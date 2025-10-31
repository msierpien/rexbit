<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseDocumentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_document_id',
        'product_id',
        'quantity',
        'unit_price',
        'vat_rate',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'meta' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(WarehouseDocument::class, 'warehouse_document_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
