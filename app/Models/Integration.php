<?php

namespace App\Models;

use App\Enums\IntegrationStatus;
use App\Enums\IntegrationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'config' => 'encrypted:array',
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
}
