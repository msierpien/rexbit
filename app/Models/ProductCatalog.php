<?php

namespace App\Models;

use App\Models\WarehouseLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCatalog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'catalog_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'catalog_id');
    }

    public function warehouseLocations(): BelongsToMany
    {
        return $this->belongsToMany(WarehouseLocation::class, 'product_catalog_warehouse_location')
            ->withTimestamps();
    }
}
