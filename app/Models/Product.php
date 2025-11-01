<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'catalog_id',
        'category_id',
        'manufacturer_id',
        'slug',
        'sku',
        'ean',
        'name',
        'description',
        'images',
        'purchase_price_net',
        'purchase_vat_rate',
        'sale_price_net',
        'sale_vat_rate',
        'status',
        'attributes',
    ];

    protected $casts = [
        'attributes' => 'array',
        'images' => 'array',
        'status' => ProductStatus::class,
        'purchase_price_net' => 'decimal:2',
        'sale_price_net' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ProductCatalog::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function getPurchasePriceGrossAttribute(): ?float
    {
        if ($this->purchase_price_net === null || $this->purchase_vat_rate === null) {
            return null;
        }

        return round($this->purchase_price_net * (1 + $this->purchase_vat_rate / 100), 2);
    }

    public function getSalePriceGrossAttribute(): ?float
    {
        if ($this->sale_price_net === null || $this->sale_vat_rate === null) {
            return null;
        }

        return round($this->sale_price_net * (1 + $this->sale_vat_rate / 100), 2);
    }
}
