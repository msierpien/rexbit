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
        'records_total',
        'records_processed',
        'records_imported',
        'records_skipped',
        'records_failed',
        'error_message',
        'log',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'records_total' => 'integer',
        'records_processed' => 'integer',
        'records_imported' => 'integer',
        'records_skipped' => 'integer',
        'records_failed' => 'integer',
        'log' => 'array',
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
