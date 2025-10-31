<?php

namespace App\Models;

use App\Models\ProductCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationImportProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_id',
        'catalog_id',
        'name',
        'format',
        'source_type',
        'source_location',
        'delimiter',
        'has_header',
        'is_active',
        'fetch_mode',
        'fetch_interval_minutes',
        'fetch_daily_at',
        'fetch_cron_expression',
        'next_run_at',
        'last_fetched_at',
        'last_headers',
        'options',
    ];

    protected $casts = [
        'has_header' => 'boolean',
        'is_active' => 'boolean',
        'fetch_interval_minutes' => 'integer',
        'fetch_daily_at' => 'datetime:H:i',
        'next_run_at' => 'datetime',
        'last_fetched_at' => 'datetime',
        'last_headers' => 'array',
        'options' => 'array',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ProductCatalog::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(IntegrationImportMapping::class, 'profile_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(IntegrationImportRun::class, 'profile_id')->latest();
    }
}
