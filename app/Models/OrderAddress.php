<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class OrderAddress extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'name',
        'company',
        'email',
        'phone',
        'street',
        'city',
        'postal_code',
        'country',
        'state',
        'vat_id',
        'pickup_point_id',
        'pickup_point_name',
        'external_address_id',
        'prestashop_data',
        'metadata'
    ];

    protected $casts = [
        'prestashop_data' => 'array',
        'metadata' => 'array'
    ];

    // 🔐 SECURITY: Dziedziczenie bezpieczeństwa z Order
    protected static function booted(): void
    {
        static::addGlobalScope('user_order_addresses', function (Builder $builder) {
            if (auth()->check()) {
                $builder->whereHas('order', function ($q) {
                    $q->where('user_id', auth()->id());
                });
            }
        });
    }

    // Relacje
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Metody pomocnicze
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->street,
            $this->postal_code . ' ' . $this->city,
            $this->country !== 'PL' ? $this->country : null
        ]);

        return implode(', ', $parts);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->company && $this->name) {
            return $this->company . ' (' . $this->name . ')';
        }
        
        return $this->company ?: $this->name ?: 'Brak danych';
    }

    public function isPickupPoint(): bool
    {
        return $this->type === 'pickup';
    }
}
