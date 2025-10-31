<?php

namespace App\Services\Catalog;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Str;

class CategoryService
{
    public function __construct(private ValidationFactory $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, array $attributes): ProductCategory
    {
        $payload = $this->validate($attributes, null, $user);

        $category = new ProductCategory($payload);
        $category->user()->associate($user);
        $category->slug = $this->generateUniqueSlug($user, $payload['catalog_id'], $payload['name'], $payload['slug'] ?? null);
        $category->depth = $this->calculateDepth($category);
        $category->save();

        return $category->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(ProductCategory $category, array $attributes): ProductCategory
    {
        $payload = $this->validate($attributes, $category);

        if (isset($payload['catalog_id'])) {
            $category->catalog_id = $payload['catalog_id'];
        }

        if (array_key_exists('parent_id', $payload)) {
            $category->parent_id = $payload['parent_id'];
            $category->depth = $this->calculateDepth($category, $payload['parent_id']);
        }

        if (! empty($payload['name']) || ! empty($payload['slug'])) {
            $category->slug = $this->generateUniqueSlug(
                $category->user,
                $category->catalog_id,
                $payload['name'] ?? $category->name,
                $payload['slug'] ?? $category->slug,
                $category
            );
        }

        $category->fill($payload)->save();

        return $category->fresh();
    }

    public function delete(ProductCategory $category): void
    {
        $category->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function validate(array $attributes, ?ProductCategory $category = null, ?User $user = null): array
    {
        $rules = [
            'catalog_id' => ['required', 'exists:product_catalogs,id'],
            'parent_id' => ['nullable', 'exists:product_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];

        $validated = $this->validator->make($attributes, $rules)->validate();

        $owner = $user ?? $category?->user;

        if ($owner && isset($validated['catalog_id']) && ! $owner->productCatalogs()->where('id', $validated['catalog_id'])->exists()) {
            throw new \InvalidArgumentException('Wybrany katalog nie istnieje.');
        }

        if (! empty($validated['parent_id'])) {
            $parent = $owner?->productCategories()->find($validated['parent_id']);

            if (! $parent) {
                throw new \InvalidArgumentException('Wybrana kategoria nadrzędna nie istnieje.');
            }

            if (isset($validated['catalog_id']) && $parent->catalog_id !== $validated['catalog_id']) {
                throw new \InvalidArgumentException('Kategoria nadrzędna należy do innego katalogu.');
            }
        }

        if (! empty($validated['parent_id']) && $category) {
            if ($this->isDescendant($validated['parent_id'], $category)) {
                throw new \InvalidArgumentException('Nie można ustawić kategorii podrzędnej jako rodzica.');
            }
        }

        return $validated;
    }

    protected function generateUniqueSlug(User $user, int $catalogId, string $name, ?string $givenSlug = null, ?ProductCategory $ignore = null): string
    {
        $base = $givenSlug ? Str::slug($givenSlug) : Str::slug($name);
        $candidate = $base;
        $counter = 1;

        while ($this->slugExists($user, $catalogId, $candidate, $ignore)) {
            $candidate = $base.'-'.$counter++;
        }

        return $candidate;
    }

    protected function slugExists(User $user, int $catalogId, string $slug, ?ProductCategory $ignore): bool
    {
        return $user->productCategories()
            ->where('catalog_id', $catalogId)
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->id))
            ->where('slug', $slug)
            ->exists();
    }

    protected function calculateDepth(ProductCategory $category, ?int $parentId = null): int
    {
        $parentId ??= $category->parent_id;

        if (! $parentId) {
            return 0;
        }

        $parent = $category->user->productCategories()->find($parentId);

        return ($parent?->depth ?? 0) + 1;
    }

    protected function isDescendant(int $candidateParentId, ProductCategory $category): bool
    {
        $current = $category;

        while ($current->parent_id) {
            if ($current->parent_id === $candidateParentId) {
                return true;
            }

            $current = $current->parent;

            if (! $current) {
                break;
            }
        }

        return false;
    }
}
