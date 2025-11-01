<?php

namespace App\Services\Integrations\Tasks;

use App\Models\Integration;
use App\Models\IntegrationTask;
use App\Services\Integrations\Import\ImportParserFactory;
use App\Services\Integrations\Import\ImportSourceResolver;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class TaskService
{
    public function __construct(
        protected ValidationFactory $validator,
        protected ImportParserFactory $parserFactory,
        protected ImportSourceResolver $sourceResolver,
    ) {
    }

    /**
     * Create a new integration task
     */
    public function create(Integration $integration, array $attributes): IntegrationTask
    {
        $data = $this->validate($attributes);

        $task = $integration->tasks()->create([
            'name' => $data['name'],
            'task_type' => $data['task_type'] ?? 'import',
            'resource_type' => $data['resource_type'] ?? 'products',
            'format' => $data['format'],
            'source_type' => $data['source_type'],
            'source_location' => $data['source_location'],
            'catalog_id' => $data['catalog_id'] ?? null,
            'delimiter' => $data['delimiter'] ?? null,
            'has_header' => $data['has_header'] ?? true,
            'is_active' => $data['is_active'] ?? false,
            'fetch_mode' => $data['fetch_mode'] ?? 'manual',
            'fetch_interval_minutes' => $data['fetch_interval_minutes'] ?? null,
            'fetch_daily_at' => $data['fetch_daily_at'] ?? null,
            'fetch_cron_expression' => $data['fetch_cron_expression'] ?? null,
            'mappings' => $data['mappings'] ?? [],
            'filters' => $data['filters'] ?? [],
            'options' => $data['options'] ?? [],
        ]);

        return $task;
    }

    /**
     * Update an existing integration task
     */
    public function update(IntegrationTask $task, array $attributes): IntegrationTask
    {
        $data = $this->validate($attributes, $task);

        $task->update([
            'name' => $data['name'],
            'task_type' => $data['task_type'] ?? $task->task_type,
            'resource_type' => $data['resource_type'] ?? $task->resource_type,
            'format' => $data['format'],
            'source_type' => $data['source_type'],
            'source_location' => $data['source_location'],
            'catalog_id' => $data['catalog_id'] ?? $task->catalog_id,
            'delimiter' => $data['delimiter'] ?? null,
            'has_header' => $data['has_header'] ?? $task->has_header,
            'is_active' => $data['is_active'] ?? $task->is_active,
            'fetch_mode' => $data['fetch_mode'] ?? $task->fetch_mode,
            'fetch_interval_minutes' => $data['fetch_interval_minutes'] ?? null,
            'fetch_daily_at' => $data['fetch_daily_at'] ?? null,
            'fetch_cron_expression' => $data['fetch_cron_expression'] ?? null,
            'mappings' => $data['mappings'] ?? $task->mappings,
            'filters' => $data['filters'] ?? $task->filters,
            'options' => $data['options'] ?? $task->options,
        ]);

        return $task->refresh();
    }

    /**
     * Refresh headers for a task
     */
    public function refreshHeaders(IntegrationTask $task): IntegrationTask
    {
        $resolved = $this->sourceResolver->resolve($task);
        $path = $resolved['path'];

        try {
            $parser = $this->parserFactory->make($task->format);

            $headers = $parser->detectHeaders($path, [
                'delimiter' => $task->delimiter,
                'has_header' => $task->has_header,
                'record_path' => Arr::get($task->options, 'record_path'),
            ]);

            $task->forceFill([
                'last_headers' => $headers,
                'last_fetched_at' => now(),
            ])->save();
        } finally {
            if ($resolved['temporary'] ?? false) {
                @unlink($path);
            }
        }

        return $task->refresh();
    }

    /**
     * Validate task data
     */
    protected function validate(array $attributes, ?IntegrationTask $task = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'task_type' => ['nullable', Rule::in(['import', 'export'])],
            'resource_type' => ['nullable', Rule::in(['products', 'orders', 'customers', 'categories', 'stock'])],
            'format' => ['required', Rule::in(['csv', 'xml', 'json'])],
            'source_type' => ['required', Rule::in(['url', 'file', 'api'])],
            'source_location' => ['required', 'string', 'max:500'],
            'catalog_id' => ['nullable', 'integer', 'exists:product_catalogs,id'],
            'delimiter' => ['nullable', 'string', 'max:5'],
            'has_header' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'fetch_mode' => ['nullable', Rule::in(['manual', 'interval', 'daily', 'cron'])],
            'fetch_interval_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'fetch_daily_at' => ['nullable', 'date_format:H:i'],
            'fetch_cron_expression' => ['nullable', 'string', 'max:120'],
            'mappings' => ['nullable', 'array'],
            'mappings.*.source_field' => ['required', 'string'],
            'mappings.*.target_field' => ['required', 'string'],
            'mappings.*.transform' => ['nullable', 'string'],
            'filters' => ['nullable', 'array'],
            'options' => ['nullable', 'array'],
        ];

        return $this->validator->make($attributes, $rules)->validate();
    }
}
