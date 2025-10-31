<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationImportRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_id',
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

    public function profile(): BelongsTo
    {
        return $this->belongsTo(IntegrationImportProfile::class, 'profile_id');
    }
}
