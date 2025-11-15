<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class OrderStatusHistory extends Model
{
    protected $table = 'order_status_history';
    
    protected $fillable = [
        'order_id',
        'changed_by',
        'from_status',
        'to_status',
        'field_name',
        'comment',
        'source',
        'ip_address',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    // 🔐 SECURITY: Dziedziczenie bezpieczeństwa z Order
    protected static function booted(): void
    {
        static::addGlobalScope('user_order_history', function (Builder $builder) {
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

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // Metody pomocnicze
    public function getChangeDescriptionAttribute(): string
    {
        $field = match($this->field_name) {
            'status' => 'Status zamówienia',
            'payment_status' => 'Status płatności',
            'fulfillment_status' => 'Status realizacji',
            default => $this->field_name
        };

        return sprintf(
            '%s zmieniony z "%s" na "%s"',
            $field,
            $this->from_status ?: 'brak',
            $this->to_status
        );
    }

    public function getChangedByNameAttribute(): string
    {
        if ($this->source === 'system') {
            return 'System';
        }

        if ($this->source === 'integration') {
            return 'Integracja';
        }

        return $this->changedBy?->name ?? 'Nieznany użytkownik';
    }
}
