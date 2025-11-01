<?php

namespace App\Models;

use App\Enums\IntegrationStatus;
use App\Enums\IntegrationType;
use App\Models\IntegrationTask;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'status',
        'config',
        'meta',
        'last_synced_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => IntegrationType::class,
        'status' => IntegrationStatus::class,
        'config' => 'array',
        'meta' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Owning user relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(IntegrationTask::class);
    }

    // Backward compatibility alias
    public function importProfiles(): HasMany
    {
        return $this->tasks()->where('task_type', 'import');
    }
}
