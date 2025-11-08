<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_id',
        'catalog_id',
        'name',
        'task_type', // import, export
        'resource_type', // products, orders, customers
        'format', // csv, xml, json
        'source_type', // url, file, api
        'source_location',
        'delimiter',
        'has_header',
        'is_active',
        'fetch_mode', // manual, interval, daily, cron
        'fetch_interval_minutes',
        'fetch_daily_at',
        'fetch_cron_expression',
        'next_run_at',
        'last_fetched_at',
        'last_headers',
        'mappings', // JSON: field mappings with transformations
        'filters', // JSON: filters for data
        'options', // JSON: additional options
    ];

    protected $casts = [
        'has_header' => 'boolean',
        'is_active' => 'boolean',
        'fetch_interval_minutes' => 'integer',
        'fetch_daily_at' => 'datetime:H:i',
        'next_run_at' => 'datetime',
        'last_fetched_at' => 'datetime',
        'last_headers' => 'array',
        'mappings' => 'array',
        'filters' => 'array',
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

    public function runs(): HasMany
    {
        return $this->hasMany(IntegrationTaskRun::class, 'task_id')->latest();
    }

    /**
     * Check if task is import type
     */
    public function isImport(): bool
    {
        return $this->task_type === 'import';
    }

    /**
     * Check if task is export type
     */
    public function isExport(): bool
    {
        return $this->task_type === 'export';
    }

    /**
     * Get formatted source label
     */
    public function getSourceLabelAttribute(): string
    {
        return match($this->source_type) {
            'url' => 'URL: ' . $this->source_location,
            'file' => 'Plik: ' . basename($this->source_location),
            'api' => 'API',
            default => $this->source_type,
        };
    }

    /**
     * Get resource type label
     */
    public function getResourceTypeLabelAttribute(): string
    {
        return match($this->resource_type) {
            'products' => 'Produkty',
            'orders' => 'Zamówienia',
            'customers' => 'Klienci',
            'categories' => 'Kategorie',
            'stock' => 'Stany magazynowe',
            'supplier-availability' => 'Dostępność dostawcy',
            default => $this->resource_type,
        };
    }
}
