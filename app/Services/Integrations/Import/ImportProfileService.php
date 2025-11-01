<?php

namespace App\Services\Integrations\Import;

use App\Models\Integration;
use App\Models\IntegrationImportProfile;
use App\Models\ProductCatalog;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ImportProfileService
{
    public function __construct(
        protected ValidationFactory $validator,
        protected ImportParserFactory $parserFactory,
        protected ImportSourceResolver $sourceResolver,
        protected ImportSchedulerService $scheduler,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Integration $integration, array $attributes): IntegrationImportProfile
    {
        $data = $this->validate($attributes);

        $sourceLocation = $this->storeSource($integration, null, $data);

        $catalogId = $this->resolveCatalog($integration, null, $data);

        $profile = new IntegrationImportProfile([
            'name' => $data['name'],
            'format' => $data['format'],
            'source_type' => $data['source_type'],
            'source_location' => $sourceLocation,
            'catalog_id' => $catalogId,
            'delimiter' => $data['delimiter'] ?? null,
            'has_header' => $data['has_header'],
            'is_active' => $data['is_active'],
            'fetch_mode' => $data['fetch_mode'],
            'fetch_interval_minutes' => $data['fetch_interval_minutes'],
            'fetch_daily_at' => $data['fetch_daily_at'],
            'fetch_cron_expression' => $data['fetch_cron_expression'],
            'options' => $data['options'] ?? [],
        ]);

        $integration->importProfiles()->save($profile);

        $this->scheduler->updateNextRun($profile);

        $this->refreshHeaders($profile);

        return $profile->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(IntegrationImportProfile $profile, array $attributes): IntegrationImportProfile
    {
        $data = $this->validate($attributes, $profile);

        $integration = $profile->integration;

        $sourceLocation = $this->storeSource($integration, $profile, $data);

        $catalogId = $this->resolveCatalog($integration, $profile, $data);

        $profile->fill([
            'name' => $data['name'],
            'format' => $data['format'],
            'source_type' => $data['source_type'],
            'source_location' => $sourceLocation,
            'catalog_id' => $catalogId,
            'delimiter' => $data['delimiter'] ?? null,
            'has_header' => $data['has_header'],
            'is_active' => $data['is_active'],
            'fetch_mode' => $data['fetch_mode'],
            'fetch_interval_minutes' => $data['fetch_interval_minutes'],
            'fetch_daily_at' => $data['fetch_daily_at'],
            'fetch_cron_expression' => $data['fetch_cron_expression'],
            'options' => $data['options'] ?? [],
        ])->save();

        $this->scheduler->updateNextRun($profile);

        if (Arr::get($data, 'refresh_headers', false)) {
            $this->refreshHeaders($profile);
        }

        return $profile->refresh();
    }

    public function refreshHeaders(IntegrationImportProfile $profile): IntegrationImportProfile
    {
        $resolved = $this->sourceResolver->resolve($profile);
        $path = $resolved['path'];

        try {
            $parser = $this->parserFactory->make($profile->format);

            $headers = $parser->detectHeaders($path, [
                'delimiter' => $profile->delimiter,
                'has_header' => $profile->has_header,
                'record_path' => Arr::get($profile->options, 'record_path'),
            ]);

            $profile->forceFill([
                'last_headers' => $headers,
                'last_fetched_at' => now(),
            ])->save();
        } finally {
            if ($resolved['temporary'] ?? false) {
                @unlink($path);
            }
        }

        return $profile->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function validate(array $attributes, ?IntegrationImportProfile $profile = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'format' => ['required', Rule::in(['csv', 'xml'])],
            'source_type' => ['required', Rule::in(['file', 'url'])],
            'source_file' => ['required_if:source_type,file', 'nullable', 'file'],
            'source_url' => ['required_if:source_type,url', 'nullable', 'url', 'max:500'],
            'delimiter' => ['nullable', 'string', 'max:5'],
            'has_header' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'fetch_mode' => ['required', Rule::in(['manual', 'interval', 'daily', 'cron'])],
            'fetch_interval_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'fetch_daily_at' => ['nullable', 'date_format:H:i'],
            'fetch_cron_expression' => ['nullable', 'string', 'max:120'],
            'options' => ['nullable', 'array'],
            'options.record_path' => ['nullable', 'string', 'max:255'],
            'refresh_headers' => ['nullable', 'boolean'],
            'catalog_id' => ['nullable', 'integer', 'exists:product_catalogs,id'],
            'new_catalog_name' => ['nullable', 'string', 'max:255'],
        ];

        if ($profile) {
            // For updates allow skipping file if already stored.
            $rules['source_file'][0] = 'nullable';
            $rules['source_url'][0] = 'nullable';
        }

        $data = $this->validator->make($attributes, $rules)->validate();

        if (($data['source_type'] ?? null) === 'file') {
            $data['source_file'] = $attributes['source_file'] ?? null;

            if (! $profile && ! ($data['source_file'] instanceof UploadedFile)) {
                throw new \InvalidArgumentException('Brak pliku do załadowania.');
            }
        }

        $data['has_header'] = array_key_exists('has_header', $data) ? (bool) $data['has_header'] : true;
        $data['is_active'] = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : false;
        $data['fetch_mode'] = $data['fetch_mode'] ?? 'manual';

        if (($data['format'] ?? '') === 'csv') {
            $data['delimiter'] = $data['delimiter'] ?? ';';
        } else {
            $data['delimiter'] = null;
        }

        if ($data['fetch_mode'] === 'interval' && empty($data['fetch_interval_minutes'])) {
            throw ValidationException::withMessages([
                'fetch_interval_minutes' => 'Dla harmonogramu cyklicznego należy podać interwał w minutach.',
            ]);
        }

        if ($data['fetch_mode'] !== 'interval') {
            $data['fetch_interval_minutes'] = null;
        }

        if ($data['fetch_mode'] !== 'daily') {
            $data['fetch_daily_at'] = null;
        }

        if ($data['fetch_mode'] !== 'cron') {
            $data['fetch_cron_expression'] = null;
        }

        return $data;
    }

    protected function resolveCatalog(Integration $integration, ?IntegrationImportProfile $profile, array $data): int
    {
        $user = $integration->user;

        if (! $user) {
            throw new \RuntimeException('Nie znaleziono właściciela integracji.');
        }

        if (! empty($data['new_catalog_name'])) {
            return $user->productCatalogs()->create([
                'name' => $data['new_catalog_name'],
                'slug' => $this->uniqueCatalogSlug($user->id, $data['new_catalog_name']),
            ])->id;
        }

        if (! empty($data['catalog_id'])) {
            $catalog = $user->productCatalogs()->where('id', $data['catalog_id'])->first();

            if (! $catalog) {
                throw ValidationException::withMessages([
                    'catalog_id' => 'Wybrany katalog nie należy do tego użytkownika.',
                ]);
            }

            return $catalog->id;
        }

        if ($profile && $profile->catalog_id) {
            return $profile->catalog_id;
        }

        $existing = $user->productCatalogs()->first();

        if ($existing) {
            return $existing->id;
        }

        return $user->productCatalogs()->create([
            'name' => 'Domyślny katalog',
            'slug' => $this->uniqueCatalogSlug($user->id, 'Domyślny katalog'),
        ])->id;
    }

    protected function uniqueCatalogSlug(int $userId, string $name): string
    {
        $base = Str::slug($name) ?: 'katalog';
        $slug = $base;
        $counter = 1;

        while (ProductCatalog::query()->where('user_id', $userId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function storeSource(Integration $integration, ?IntegrationImportProfile $profile, array $data): string
    {
        if (($data['source_type'] ?? null) === 'file') {
            /** @var UploadedFile|null $file */
            $file = Arr::get($data, 'source_file');

            if ($file instanceof UploadedFile) {
                $path = $file->store(
                    sprintf('integrations/%d/imports', $integration->user_id)
                );

                if ($profile && $profile->source_type === 'file' && $profile->source_location && Storage::disk('local')->exists($profile->source_location)) {
                    Storage::disk('local')->delete($profile->source_location);
                }

                return $path;
            }

            if ($profile) {
                return $profile->source_location;
            }

            throw new \InvalidArgumentException('Nie dostarczono pliku źródłowego.');
        }

        return $data['source_url'] ?? $profile?->source_location ?? '';
    }
}
