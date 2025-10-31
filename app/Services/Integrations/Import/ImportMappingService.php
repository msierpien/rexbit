<?php

namespace App\Services\Integrations\Import;

use App\Models\IntegrationImportMapping;
use App\Models\IntegrationImportProfile;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ImportMappingService
{
    public function __construct(
        protected ValidationFactory $validator,
    ) {
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $mappings
     */
    public function sync(IntegrationImportProfile $profile, array $mappings): void
    {
        $availableHeaders = $profile->last_headers ?? [];

        $allowedProductFields = ['sku', 'name', 'description', 'sale_price_net', 'sale_vat_rate', 'purchase_price_net', 'purchase_vat_rate', 'category_slug', 'category_name'];
        $allowedCategoryFields = ['slug', 'name', 'parent_slug', 'parent_name'];

        $normalized = $this->normalizeInput($mappings);

        $payload = $this->validator->make($normalized, [
            'product' => ['nullable', 'array'],
            'product.*.source_field' => ['required', 'string', 'max:255'],
            'product.*.target_field' => ['required', 'string', Rule::in($allowedProductFields)],
            'product.*.transform' => ['nullable', 'array'],
            'category' => ['nullable', 'array'],
            'category.*.source_field' => ['required', 'string', 'max:255'],
            'category.*.target_field' => ['required', 'string', Rule::in($allowedCategoryFields)],
            'category.*.transform' => ['nullable', 'array'],
        ])->validate();

        $this->ensureHeadersExist($availableHeaders, $payload);

        $profile->mappings()->delete();

        foreach (['product', 'category'] as $target) {
            foreach (Arr::get($payload, $target, []) as $mapping) {
                IntegrationImportMapping::create([
                    'profile_id' => $profile->id,
                    'target_type' => $target,
                    'source_field' => $mapping['source_field'],
                    'target_field' => $mapping['target_field'],
                    'transform' => $mapping['transform'] ?? null,
                ]);
            }
        }
    }

    protected function ensureHeadersExist(array $headers, array $payload): void
    {
        if (empty($headers)) {
            return;
        }

        $headerSet = array_map('strtolower', $headers);

        $missing = [];

        foreach (['product', 'category'] as $target) {
            foreach (Arr::get($payload, $target, []) as $mapping) {
                $header = strtolower($mapping['source_field']);
                if (! in_array($header, $headerSet, true)) {
                    $missing[] = $mapping['source_field'];
                }
            }
        }

        if (! empty($missing)) {
            throw new \InvalidArgumentException('Brak wskazanych kolumn w źródle: '.implode(', ', array_unique($missing)));
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function normalizeInput(array $input): array
    {
        $normalized = [];

        foreach (['product', 'category'] as $target) {
            if (! isset($input[$target])) {
                continue;
            }

            $value = $input[$target];

            if (Arr::isAssoc($value)) {
                $normalized[$target] = collect($value)
                    ->filter(fn ($source) => $source !== null && $source !== '')
                    ->map(fn ($source, $targetField) => [
                        'source_field' => $source,
                        'target_field' => $targetField,
                    ])->values()->all();
            } else {
                $normalized[$target] = $value;
            }
        }

        return $normalized;
    }
}
