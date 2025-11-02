<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'is_manual' => 'boolean',
        'metadata' => 'array',
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
}
