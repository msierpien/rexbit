<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'integration_id',
        'number',
        'external_order_id',
        'external_reference',
        'status',
        'payment_status',
        'fulfillment_status',
        'customer_name',
        'customer_email',
        'customer_phone',
        'currency',
        'total_net',
        'total_gross',
        'total_paid',
        'shipping_cost',
        'discount_total',
        'prestashop_data',
        'notes',
        'metadata',
        'payment_method',
        'is_paid',
        'shipping_method',
        'shipping_details',
        'invoice_data',
        'is_company',
        'order_date',
        'paid_at',
        'shipped_at',
        'completed_at'
    ];

    protected $casts = [
        'prestashop_data' => 'array',
        'metadata' => 'array',
        'shipping_details' => 'array',
        'invoice_data' => 'array',
        'is_paid' => 'boolean',
        'is_company' => 'boolean',
        'order_date' => 'datetime',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_net' => 'decimal:4',
        'total_gross' => 'decimal:4',
        'total_paid' => 'decimal:4',
        'shipping_cost' => 'decimal:4',
        'discount_total' => 'decimal:4'
    ];

    // 🔐 SECURITY: Global scope - użytkownik widzi tylko swoje zamówienia
    protected static function booted(): void
    {
        static::addGlobalScope('user_orders', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });

        // Auto-generowanie numeru zamówienia
        static::creating(function (Order $order) {
            if (empty($order->number)) {
                $order->number = static::generateOrderNumber($order->user_id);
            }
            
            if (empty($order->user_id) && auth()->check()) {
                $order->user_id = auth()->id();
            }
        });
    }

    // Relacje
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }
    
    public function shippingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'shipping');
    }
    
    public function billingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'billing');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function orderStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'status', 'key')->where('type', 'order');
    }

    public function paymentStatusModel(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'payment_status', 'key')->where('type', 'payment');
    }

    // Metody pomocnicze
    public function getBillingAddressAttribute(): ?OrderAddress
    {
        return $this->addresses()->where('type', 'billing')->first();
    }

    public function getShippingAddressAttribute(): ?OrderAddress
    {
        return $this->addresses()->where('type', 'shipping')->first();
    }

    public function getPickupPointAttribute(): ?OrderAddress
    {
        return $this->addresses()->where('type', 'pickup')->first();
    }

    public function getItemsCountAttribute(): int
    {
        return $this->items()->sum('quantity');
    }

    public function getIsFromIntegrationAttribute(): bool
    {
        return !is_null($this->integration_id);
    }

    // Sprawdź czy zamówienie należy do zalogowanego użytkownika
    public function belongsToCurrentUser(): bool
    {
        return $this->user_id === auth()->id();
    }

    // Zmiana statusu z historią
    public function changeStatus(string $newStatus, ?string $comment = null, ?User $changedBy = null): bool
    {
        $oldStatus = $this->status;
        
        if ($oldStatus === $newStatus) {
            return false; // Brak zmiany
        }

        // Zapisz historię przed zmianą
        OrderStatusHistory::create([
            'order_id' => $this->id,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'field_name' => 'status',
            'comment' => $comment,
            'changed_by' => $changedBy?->id ?? auth()->id(),
            'source' => $changedBy ? 'user' : 'system',
            'ip_address' => request()->ip()
        ]);

        // Aktualizuj status
        $this->update(['status' => $newStatus]);

        return true;
    }

    // Generowanie unikalnego numeru zamówienia
    public static function generateOrderNumber(int $userId): string
    {
        $prefix = 'ORD';
        $year = date('Y');
        $month = date('m');
        
        // Format: ORD-2025-11-0001
        $lastOrder = static::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderByDesc('id')
            ->first();

        $sequence = 1;
        if ($lastOrder && preg_match('/(\d+)$/', $lastOrder->number, $matches)) {
            $sequence = (int)$matches[1] + 1;
        }

        return sprintf('%s-%s-%s-%04d', $prefix, $year, $month, $sequence);
    }

    // Scopes dla filtrowania (z zachowaniem bezpieczeństwa)
    public function scopeForIntegration(Builder $query, int $integrationId): Builder
    {
        return $query->where('integration_id', $integrationId)
                    ->whereHas('integration', function ($q) {
                        $q->where('user_id', auth()->id()); // Dodatkowe zabezpieczenie
                    });
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeWithTotalItems(Builder $query): Builder
    {
        return $query->withCount(['items as items_count' => function ($q) {
            $q->selectRaw('sum(quantity)');
        }]);
    }
}
