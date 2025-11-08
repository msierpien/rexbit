<?php

namespace App\Services\Integrations\Tasks;

use App\Models\Integration;
use App\Models\IntegrationTask;
use App\Services\Integrations\Import\ImportParserFactory;
use App\Services\Integrations\Import\ImportSourceResolver;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
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
        $normalized = $this->normalizeAttributes($attributes);
        $data = $this->validate($normalized, integration: $integration);

        $sourceLocation = $this->storeSource($integration, null, $data);

        $task = $integration->tasks()->create([
            'name' => $data['name'],
            'task_type' => $data['task_type'] ?? 'import',
            'resource_type' => $data['resource_type'] ?? 'products',
            'format' => $data['format'],
            'source_type' => $data['source_type'],
            'source_location' => $sourceLocation,
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

        $this->maybeRefreshHeaders($task, $data, true);

        return $task->refresh();
    }

    /**
     * Update an existing integration task
     */
    public function update(IntegrationTask $task, array $attributes): IntegrationTask
    {
        $normalized = $this->normalizeAttributes($attributes);
        $data = $this->validate($normalized, $task, $task->integration);

        $sourceLocation = $this->storeSource($task->integration, $task, $data);

        $task->update([
            'name' => $data['name'],
            'task_type' => $data['task_type'] ?? $task->task_type,
            'resource_type' => $data['resource_type'] ?? $task->resource_type,
            'format' => $data['format'],
            'source_type' => $data['source_type'],
            'source_location' => $sourceLocation,
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

        $this->maybeRefreshHeaders($task, $data, false);

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
    protected function validate(array $attributes, ?IntegrationTask $task = null, ?Integration $integration = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'task_type' => ['nullable', Rule::in(['import', 'export'])],
            'resource_type' => ['nullable', Rule::in(['products', 'orders', 'customers', 'categories', 'stock', 'supplier-availability'])],
            'format' => ['required', Rule::in(['csv', 'xml', 'json'])],
            'source_type' => ['required', Rule::in(['url', 'file', 'api'])],
            'source_location' => ['required_unless:source_type,file', 'nullable', 'string', 'max:500'],
            'source_file' => ['required_if:source_type,file', 'nullable', 'file'],
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

        if (($attributes['resource_type'] ?? 'products') === 'supplier-availability') {
            $contractorRule = ['nullable', 'integer', 'exists:contractors,id'];
            if ($integration?->user_id) {
                $contractorRule = [
                    'nullable',
                    Rule::exists('contractors', 'id')->where('user_id', $integration->user_id),
                ];
            }

            $rules = array_merge($rules, [
                'options.supplier_availability' => ['nullable', 'array'],
                'options.supplier_availability.contractor_id' => $contractorRule,
                'options.supplier_availability.match_by' => ['nullable', Rule::in(['sku', 'ean', 'sku_or_ean'])],
                'options.supplier_availability.missing_behavior' => ['nullable', Rule::in(['skip', 'error'])],
                'options.supplier_availability.default_delivery_days' => ['nullable', 'integer', 'min:0', 'max:365'],
                'options.supplier_availability.sync_purchase_price' => ['nullable', 'boolean'],
            ]);
        }

        return $this->validator->make($attributes, $rules)->validate();
    }

    /**
     * Normalize incoming attributes before validation.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function normalizeAttributes(array $attributes): array
    {
        if (($attributes['source_type'] ?? null) === 'file') {
            $uploadedFile = $attributes['source_file'] ?? null;

            if (! $uploadedFile instanceof UploadedFile && ($attributes['source_location'] ?? null) instanceof UploadedFile) {
                $uploadedFile = $attributes['source_location'];
            }

            if ($uploadedFile instanceof UploadedFile) {
                $attributes['source_file'] = $uploadedFile;
            }

            if (($attributes['source_location'] ?? null) instanceof UploadedFile) {
                unset($attributes['source_location']);
            }
        }

        return $attributes;
    }

    /**
     * Store uploaded source file (if provided) and return path.
     *
     * @param  array<string, mixed>  $data
     */
    protected function storeSource(Integration $integration, ?IntegrationTask $task, array $data): string
    {
        if (($data['source_type'] ?? null) !== 'file') {
            return $data['source_location'] ?? ($task?->source_location ?? '');
        }

        /** @var UploadedFile|null $file */
        $file = Arr::get($data, 'source_file');

        if ($file instanceof UploadedFile) {
            $userId = $integration->user_id ?? $integration->user?->id;

            if (! $userId) {
                throw new \RuntimeException('Nie można zapisać pliku importu bez przypisanego użytkownika integracji.');
            }

            $path = $file->store(
                sprintf('integrations/%d/imports', $userId),
                'local'
            );

            if ($task && $task->source_type === 'file' && $task->source_location) {
                $disk = Storage::disk('local');
                if ($disk->exists($task->source_location)) {
                    $disk->delete($task->source_location);
                }
            }

            return $path;
        }

        if ($task) {
            return $task->source_location;
        }

        throw new \InvalidArgumentException('Nie dostarczono pliku źródłowego do importu.');
    }

    /**
     * Refresh headers automatically when needed.
     *
     * @param  array<string, mixed>  $data
     */
    protected function maybeRefreshHeaders(IntegrationTask $task, array $data, bool $isNew): void
    {
        $shouldRefresh = $isNew;
        $sourceType = $data['source_type'] ?? $task->source_type;

        if (($data['source_type'] ?? null) === 'file' && Arr::get($data, 'source_file') instanceof UploadedFile) {
            $shouldRefresh = true;
        }

        if (Arr::get($data, 'refresh_headers', false)) {
            $shouldRefresh = true;
        }

        if (! $shouldRefresh || ! in_array($sourceType, ['file', 'url'], true)) {
            return;
        }

        try {
            $this->refreshHeaders($task);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
