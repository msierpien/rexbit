<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class OrderStatus extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'name',
        'color',
        'description',
        'type',
        'is_default',
        'is_final',
        'sort_order',
        'is_active',
        'is_system',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_final' => 'boolean',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    // 🔐 SECURITY: Automatycznie filtruj po user_id
    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });

        static::creating(function (OrderStatus $status) {
            if (!$status->user_id && auth()->check()) {
                $status->user_id = auth()->id();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeForOrders(Builder $query): Builder
    {
        return $query->where('type', 'order');
    }

    public function scopeForPayments(Builder $query): Builder
    {
        return $query->where('type', 'payment');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Metody pomocnicze
    public static function getOrderStatuses(): array
    {
        return static::forOrders()
            ->active()
            ->ordered()
            ->get()
            ->map(fn($status) => [
                'key' => $status->key,
                'name' => $status->name,
                'color' => $status->color,
                'is_default' => $status->is_default,
                'is_final' => $status->is_final,
            ])
            ->toArray();
    }

    public static function getPaymentStatuses(): array
    {
        return static::forPayments()
            ->active()
            ->ordered()
            ->get()
            ->map(fn($status) => [
                'key' => $status->key,
                'name' => $status->name,
                'color' => $status->color,
                'is_default' => $status->is_default,
                'is_final' => $status->is_final,
            ])
            ->toArray();
    }

    public static function getDefaultOrderStatus(): ?string
    {
        $status = static::forOrders()->default()->first();
        return $status ? $status->key : 'awaiting_payment';
    }

    public static function getDefaultPaymentStatus(): ?string
    {
        $status = static::forPayments()->default()->first();
        return $status ? $status->key : 'pending';
    }

    public static function findByKey(string $key, string $type): ?self
    {
        return static::where('key', $key)->where('type', $type)->first();
    }
}