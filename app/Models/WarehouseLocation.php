<?php

namespace App\Models;

use App\Models\ProductCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'code',
        'is_default',
        'strict_control',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'strict_control' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(WarehouseDocument::class);
    }

    public function catalogs(): BelongsToMany
    {
        return $this->belongsToMany(ProductCatalog::class, 'product_catalog_warehouse_location')
            ->withTimestamps();
    }
}
