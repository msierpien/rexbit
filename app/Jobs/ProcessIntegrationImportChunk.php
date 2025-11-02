<?php

namespace App\Jobs;

use App\Enums\ProductStatus;
use App\Models\IntegrationImportRun;
use App\Models\IntegrationTaskRun;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Notifications\IntegrationImportFinished;
use App\Services\Integrations\Import\ImportRunService;
use App\Services\Integrations\Import\ImportSchedulerService;
use App\Services\Integrations\Tasks\TaskRunService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class ProcessIntegrationImportChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $runId,
        public array $rows,
        public array $productMappings,
        public array $categoryMappings,
        public ?int $catalogId = null
    ) {
    }

    public function handle(
        TaskRunService $taskRunService,
        ImportRunService $importRunService,
        ImportSchedulerService $scheduler
    ): void
    {
        $run = IntegrationTaskRun::with(['task.integration.user'])->find($this->runId);
        $isTaskRun = true;
        $user = null;
        $catalogId = null;
        $scheduleTarget = null;

        if (! $run) {
            $run = IntegrationImportRun::with(['profile.integration.user'])->find($this->runId);
            $isTaskRun = false;
        }

        if (! $run) {
            return;
        }

        if ($isTaskRun) {
            $user = $run->task?->integration?->user;
            $scheduleTarget = $run->task;
            $catalogId = $this->catalogId ?? $run->task?->catalog_id;
        } else {
            $user = $run->profile?->integration?->user;
            $scheduleTarget = $run->profile;
            $catalogId = $this->catalogId ?? $run->profile?->catalog_id;
        }

        if (! $user) {
            return;
        }

        $catalogId = $catalogId ?? $this->ensureCatalog($user)->id;

        $processed = 0;
        $success = 0;
        $failure = 0;
        $samples = [];
        $errors = [];

        foreach ($this->rows as $row) {
            $processed++;

            try {
                $productPayload = $this->mapRow($row, $this->productMappings);
                $categoryPayload = $this->mapRow($row, $this->categoryMappings);

                $result = $this->persistRecord(
                    $user,
                    $catalogId,
                    $productPayload,
                    $categoryPayload
                );

                if ($result) {
                    $success++;

                    if (count($samples) < 5) {
                        $samples[] = [
                            'product' => $result['product'],
                            'category' => $result['category'],
                        ];
                    }
                }
            } catch (Throwable $exception) {
                $failure++;
                if (count($errors) < 5) {
                    $errors[] = $exception->getMessage();
                }
            }
        }

        if ($isTaskRun) {
            $run = $taskRunService->applyChunkResult(
                $run,
                $processed,
                $success,
                $failure,
                $samples,
                $errors
            );
        } else {
            $run = $importRunService->applyChunkResult(
                $run->id,
                $processed,
                $success,
                $failure,
                $samples,
                $errors
            );
        }

        if (($run->meta['pending_chunks'] ?? 0) === 0) {
            if ($run->status === 'completed') {
                $message = $run->failure_count > 0
                    ? 'Import zakończony z błędami.'
                    : 'Import zakończony pomyślnie.';

                $run->forceFill(['message' => $run->message ?? $message])->save();

                if ($scheduleTarget) {
                    $scheduleTarget->forceFill(['last_fetched_at' => now()])->save();
                    $scheduler->updateNextRun($scheduleTarget);
                }

                if ($user) {
                    $user->notify(new IntegrationImportFinished($run));
                }
            } elseif ($run->status === 'failed') {
                if ($user) {
                    $user->notify(new IntegrationImportFinished($run, false, $run->message));
                }
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $taskRunService = app(TaskRunService::class);
        $importRunService = app(ImportRunService::class);

        $run = IntegrationTaskRun::with(['task.integration.user'])->find($this->runId);
        $user = null;

        if ($run) {
            $taskRunService->fail($run, $exception->getMessage());
            $user = $run->task?->integration?->user;
        } else {
            $run = IntegrationImportRun::with(['profile.integration.user'])->find($this->runId);
            if ($run) {
                $importRunService->fail($run, $exception->getMessage());
                $user = $run->profile?->integration?->user;
            }
        }

        if ($run && $user) {
            $user->notify(new IntegrationImportFinished($run, false, $exception->getMessage()));
        }
    }

    /**
     * @param  array<string, string>  $mappings
     * @return array<string, mixed>
     */
    protected function mapRow(array $row, array $mappings): array
    {
        $payload = [];

        foreach ($mappings as $target => $source) {
            if (! $source) {
                continue;
            }
            $payload[$target] = $row[$source] ?? null;
        }

        return $payload;
    }

    /**
     * @return array{product: array<string, mixed>, category: array<string, mixed>}|null
     */
    protected function persistRecord(
        $user,
        int $catalogId,
        array $productPayload,
        array $categoryPayload
    ): ?array {
        $productPayload = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $productPayload);
        $categoryPayload = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $categoryPayload);

        if (empty($productPayload) && empty($categoryPayload)) {
            return null;
        }

        $categoryPayload = $this->mergeCategoryData($productPayload, $categoryPayload);
        $categoryId = $this->resolveCategory($user, $catalogId, $categoryPayload);

        [$product, $normalizedProduct] = $this->storeProduct($user, $catalogId, $productPayload, $categoryId);

        return [
            'product' => $normalizedProduct,
            'category' => $categoryPayload,
        ];
    }

    protected function mergeCategoryData(array &$productPayload, array $categoryPayload): array
    {
        $categoryPayload['slug'] = $categoryPayload['slug'] ?? ($productPayload['category_slug'] ?? null);
        $categoryPayload['name'] = $categoryPayload['name'] ?? ($productPayload['category_name'] ?? null);

        unset($productPayload['category_slug'], $productPayload['category_name']);

        return $categoryPayload;
    }

    protected function resolveCategory($user, int $catalogId, array $categoryPayload): ?int
    {
        $slug = $categoryPayload['slug'] ?? null;
        $name = $categoryPayload['name'] ?? null;

        if (! $slug && $name) {
            $slug = Str::slug($name);
        }

        if (! $slug) {
            return null;
        }

        $slug = Str::slug($slug);

        if ($slug === '') {
            return null;
        }

        $parentSlug = $categoryPayload['parent_slug'] ?? null;
        $parentName = $categoryPayload['parent_name'] ?? null;

        if (! $parentSlug && $parentName) {
            $parentSlug = Str::slug($parentName);
        }

        $parentId = null;

        if ($parentSlug) {
            $parentId = $this->findOrCreateCategory($user, $catalogId, $parentSlug, $parentName);
        }

        return $this->findOrCreateCategory($user, $catalogId, $slug, $name, $parentId);
    }

    protected function findOrCreateCategory($user, int $catalogId, ?string $slug, ?string $name, ?int $parentId = null): ?int
    {
        if (! $slug) {
            return null;
        }

        $slug = Str::slug($slug);

        if ($slug === '') {
            return null;
        }

        $category = ProductCategory::query()
            ->where('user_id', $user->id)
            ->where('catalog_id', $catalogId)
            ->where('slug', $slug)
            ->first();

        $displayName = $name ?: Str::title(str_replace('-', ' ', $slug));

        if (! $category) {
            $category = new ProductCategory([
                'user_id' => $user->id,
                'catalog_id' => $catalogId,
                'slug' => $slug,
            ]);
        }

        $category->fill([
            'name' => $displayName,
        ]);

        if ($parentId !== null) {
            $category->parent_id = $parentId;
        }

        $category->save();

        return $category->id;
    }

    /**
     * @return array{0: Product, 1: array<string, mixed>}
     */
    protected function storeProduct($user, int $catalogId, array $productPayload, ?int $categoryId): array
    {
        $sku = Arr::get($productPayload, 'sku');
        $name = Arr::get($productPayload, 'name');

        if ($sku) {
            $sku = (string) $sku;
        }

        if (! $sku && $name) {
            $sku = Str::upper(Str::slug($name, '_'));
        }

        if (! $sku) {
            throw new \RuntimeException('Brak wartości SKU – pominięto rekord.');
        }

        if (! $name) {
            throw new \RuntimeException('Brak nazwy produktu – pominięto rekord.');
        }

        $product = Product::query()
            ->where('user_id', $user->id)
            ->where('catalog_id', $catalogId)
            ->where('sku', $sku)
            ->first();

        $isNew = false;

        if (! $product) {
            $product = new Product([
                'user_id' => $user->id,
                'catalog_id' => $catalogId,
                'sku' => $sku,
            ]);
            $product->slug = $this->ensureUniqueProductSlug($user->id, $catalogId, Str::slug($name));
            $isNew = true;
        }

        $productData = [
            'name' => $name,
            'description' => Arr::get($productPayload, 'description'),
            'ean' => Arr::get($productPayload, 'ean'),
            'images' => $this->parseImages(Arr::get($productPayload, 'images')),
            'sale_price_net' => $this->parseDecimal(Arr::get($productPayload, 'sale_price_net')),
            'sale_vat_rate' => $this->parseInteger(Arr::get($productPayload, 'sale_vat_rate')),
            'purchase_price_net' => $this->parseDecimal(Arr::get($productPayload, 'purchase_price_net')),
            'purchase_vat_rate' => $this->parseInteger(Arr::get($productPayload, 'purchase_vat_rate')),
        ];

        $product->fill(array_filter($productData, fn ($value) => $value !== null && $value !== ''));

        if ($categoryId) {
            $product->category_id = $categoryId;
        }

        if (! $product->status) {
            $product->status = ProductStatus::ACTIVE;
        }

        $product->save();

        return [$product, [
            'sku' => $product->sku,
            'name' => $product->name,
            'sale_price_net' => $product->sale_price_net,
            'sale_vat_rate' => $product->sale_vat_rate,
            'category_id' => $product->category_id,
            'created' => $isNew,
        ]];
    }

    protected function parseDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], (string) $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    protected function parseInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    protected function parseImages($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Jeśli to już tablica, zwróć ją
        if (is_array($value)) {
            return array_filter($value, fn($img) => !empty($img));
        }

        // Jeśli to string, spróbuj sparsować jako JSON lub rozdziel po przecinkach
        if (is_string($value)) {
            // Spróbuj JSON
            $json = json_decode($value, true);
            if (is_array($json)) {
                return array_filter($json, fn($img) => !empty($img));
            }

            // Spróbuj rozdzielić po przecinkach lub średnikach
            $urls = preg_split('/[,;]/', $value);
            $urls = array_map('trim', $urls);
            $urls = array_filter($urls, fn($url) => !empty($url) && filter_var($url, FILTER_VALIDATE_URL));
            
            return !empty($urls) ? array_values($urls) : null;
        }

        return null;
    }

    protected function ensureCatalog($user)
    {
        $catalog = $user->productCatalogs()->first();

        if (! $catalog) {
            $catalog = $user->productCatalogs()->create([
                'name' => 'Domyślny katalog',
                'slug' => 'default-'.$user->id,
            ]);
        }

        return $catalog;
    }

    protected function ensureUniqueProductSlug(int $userId, int $catalogId, string $baseSlug): string
    {
        $slug = $baseSlug !== '' ? $baseSlug : 'produkt';
        $original = $slug;
        $counter = 1;

        while (Product::query()
            ->where('user_id', $userId)
            ->where('catalog_id', $catalogId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
