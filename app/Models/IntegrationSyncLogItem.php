<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationSyncLogItem extends Model
{
    protected $fillable = [
        'sync_log_id',
        'product_id',
        'external_id',
        'status',
        'quantity',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'quantity' => 'decimal:2',
    ];

    public function syncLog(): BelongsTo
    {
        return $this->belongsTo(IntegrationSyncLog::class, 'sync_log_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
