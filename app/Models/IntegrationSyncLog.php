<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationSyncLog extends Model
{
    protected $fillable = [
        'integration_id',
        'user_id',
        'type',
        'direction',
        'status',
        'total_items',
        'success_count',
        'failed_count',
        'skipped_count',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(IntegrationSyncLogItem::class, 'sync_log_id');
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => $this->failed_count > 0 ? 'completed_with_errors' : 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], ['error' => $error]),
        ]);
    }

    public function incrementSuccess(): void
    {
        $this->increment('success_count');
    }

    public function incrementFailed(): void
    {
        $this->increment('failed_count');
    }

    public function incrementSkipped(): void
    {
        $this->increment('skipped_count');
    }
}
