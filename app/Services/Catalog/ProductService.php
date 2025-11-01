<?php

namespace App\Services\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(private ValidationFactory $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, array $attributes): Product
    {
        $payload = $this->validate($attributes);
        $this->assertOwnership($user, $payload);

        // Process images field - convert comma-separated string to array
        if (isset($payload['images'])) {
            if (is_string($payload['images']) && !empty($payload['images'])) {
                $payload['images'] = array_filter(
                    array_map('trim', explode(',', $payload['images'])),
                    fn($url) => !empty($url)
                );
            } elseif (empty($payload['images'])) {
                $payload['images'] = null;
            }
        }

        $product = new Product($payload);
        $product->user()->associate($user);
        $product->slug = $this->generateUniqueSlug($user, $payload['catalog_id'], $payload['name'], $payload['slug'] ?? null);
        $product->status = $payload['status'] ?? ProductStatus::DRAFT;

        $product->save();

        return $product->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Product $product, array $attributes): Product
    {
        $payload = $this->validate($attributes, $product);
        $this->assertOwnership($product->user, $payload, $product);

        if (! empty($payload['name']) || ! empty($payload['slug'])) {
            $product->slug = $this->generateUniqueSlug(
                $product->user,
                $payload['catalog_id'] ?? $product->catalog_id,
                $payload['name'] ?? $product->name,
                $payload['slug'] ?? $product->slug,
                $product
            );
        }

        // Process images field - convert comma-separated string to array
        if (isset($payload['images'])) {
            if (is_string($payload['images']) && !empty($payload['images'])) {
                $payload['images'] = array_filter(
                    array_map('trim', explode(',', $payload['images'])),
                    fn($url) => !empty($url)
                );
            } elseif (empty($payload['images'])) {
                $payload['images'] = null;
            }
        }

        $product->fill($payload)->save();

        return $product->fresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function validate(array $attributes, ?Product $product = null): array
    {
        $rules = [
            'catalog_id' => ['required', 'exists:product_catalogs,id'],
            'manufacturer_id' => ['nullable', 'exists:manufacturers,id'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'slug' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:255'],
            'ean' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'images' => ['nullable', 'string'],
            'purchase_price_net' => ['nullable', 'numeric', 'min:0'],
            'purchase_vat_rate' => ['nullable', 'integer', 'between:0,99'],
            'sale_price_net' => ['nullable', 'numeric', 'min:0'],
            'sale_vat_rate' => ['nullable', 'integer', 'between:0,99'],
            'status' => ['nullable', 'in:'.implode(',', array_column(ProductStatus::cases(), 'value'))],
            'attributes' => ['nullable', 'array'],
        ];

        return $this->validator->make($attributes, $rules)->validate();
    }

    protected function generateUniqueSlug(User $user, int $catalogId, string $name, ?string $givenSlug = null, ?Product $ignore = null): string
    {
        $base = $givenSlug ? Str::slug($givenSlug) : Str::slug($name);
        $candidate = $base;
        $counter = 1;

        while ($this->slugExists($user, $catalogId, $candidate, $ignore)) {
            $candidate = $base.'-'.$counter++;
        }

        return $candidate;
    }

    protected function slugExists(User $user, int $catalogId, string $slug, ?Product $ignore): bool
    {
        return $user->products()
            ->where('catalog_id', $catalogId)
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->id))
            ->where('slug', $slug)
            ->exists();
    }

    protected function assertOwnership(User $user, array $payload, ?Product $product = null): void
    {
        if (isset($payload['catalog_id']) && ! $user->productCatalogs()->where('id', $payload['catalog_id'])->exists()) {
            throw new \InvalidArgumentException('Wybrany katalog nie istnieje.');
        }

        if (isset($payload['category_id'])) {
            $category = $user->productCategories()->where('id', $payload['category_id'])->first();

            if (! $category) {
                throw new \InvalidArgumentException('Wybrana kategoria nie istnieje.');
            }

            if (isset($payload['catalog_id']) && $category->catalog_id !== $payload['catalog_id']) {
                throw new \InvalidArgumentException('Kategoria należy do innego katalogu.');
            }
        }

        if (isset($payload['manufacturer_id']) && $payload['manufacturer_id'] !== null && ! $user->manufacturers()->where('id', $payload['manufacturer_id'])->exists()) {
            throw new \InvalidArgumentException('Wybrany producent nie istnieje.');
        }
    }
}
