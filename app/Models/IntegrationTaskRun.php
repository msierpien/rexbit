<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationTaskRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'status',
        'started_at',
        'finished_at',
        'processed_count',
        'success_count',
        'failure_count',
        'message',
        'meta',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'processed_count' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
        'meta' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(IntegrationTask::class, 'task_id');
    }

    /**
     * Get duration in seconds
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at || !$this->finished_at) {
            return null;
        }

        return $this->finished_at->diffInSeconds($this->started_at);
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): ?string
    {
        $duration = $this->duration;
        
        if ($duration === null) {
            return null;
        }

        if ($duration < 60) {
            return $duration . 's';
        }

        if ($duration < 3600) {
            return round($duration / 60, 1) . 'min';
        }

        return round($duration / 3600, 1) . 'h';
    }

    /**
     * Check if run is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if run is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if run failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
