<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contractor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'tax_id',
        'email',
        'phone',
        'city',
        'street',
        'postal_code',
        'is_supplier',
        'is_customer',
        'meta',
    ];

    protected $casts = [
        'is_supplier' => 'boolean',
        'is_customer' => 'boolean',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouseDocuments(): HasMany
    {
        return $this->hasMany(WarehouseDocument::class);
    }
}
