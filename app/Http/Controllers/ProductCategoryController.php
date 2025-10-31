<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Services\Catalog\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ProductCategoryController extends Controller
{
    public function __construct(private CategoryService $service)
    {
        $this->middleware('auth');
        $this->authorizeResource(ProductCategory::class, 'product_category');
    }

    public function index(Request $request): Response
    {
        $catalogs = $request->user()->productCatalogs()->orderBy('name')->get();
        $categories = $request->user()->productCategories()->orderBy('position')->get();

        $treeByCatalog = $categories
            ->groupBy('catalog_id')
            ->map(fn (Collection $items) => $this->buildTree($items));

        return Inertia::render('Products/Categories/Index', [
            'catalogs' => $catalogs->map(fn ($catalog) => [
                'id' => $catalog->id,
                'name' => $catalog->name,
                'slug' => $catalog->slug,
                'categories' => $treeByCatalog->get($catalog->id, []),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $catalogs = $request->user()->productCatalogs()->orderBy('name')->get();
        $categories = $request->user()->productCategories()->with('catalog')->orderBy('name')->get();

        return Inertia::render('Products/Categories/Create', [
            'catalogs' => $catalogs->map(fn ($catalog) => $catalog->only(['id', 'name']))->values(),
            'categories' => $categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'catalog_id' => $category->catalog_id,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->service->create($request->user(), $request->all());

        return redirect()->route('product-categories.index')->with('status', 'Kategoria została utworzona.');
    }

    public function edit(ProductCategory $product_category, Request $request): Response
    {
        $catalogs = $request->user()->productCatalogs()->orderBy('name')->get();
        $categories = $request->user()->productCategories()
            ->where('id', '!=', $product_category->id)
            ->with('catalog')
            ->where('catalog_id', $product_category->catalog_id)
            ->orderBy('name')
            ->get();

        return Inertia::render('Products/Categories/Edit', [
            'category' => [
                'id' => $product_category->id,
                'name' => $product_category->name,
                'slug' => $product_category->slug,
                'catalog_id' => $product_category->catalog_id,
                'parent_id' => $product_category->parent_id,
                'position' => $product_category->position,
            ],
            'catalogs' => $catalogs->map(fn ($catalog) => $catalog->only(['id', 'name']))->values(),
            'categories' => $categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'catalog_id' => $category->catalog_id,
            ])->values(),
        ]);
    }

    public function update(Request $request, ProductCategory $product_category): RedirectResponse
    {
        $this->service->update($product_category, $request->all());

        return redirect()->route('product-categories.index')->with('status', 'Kategoria została zaktualizowana.');
    }

    public function destroy(ProductCategory $product_category): RedirectResponse
    {
        $this->service->delete($product_category);

        return redirect()->route('product-categories.index')->with('status', 'Kategoria została usunięta.');
    }

    protected function buildTree(Collection $categories, ?int $parentId = null): array
    {
        $grouped = $categories->groupBy('parent_id');

        $build = function (?int $parent) use (&$build, $grouped) {
            return ($grouped->get($parent) ?? collect())
                ->sortBy('position')
                ->map(fn (ProductCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'position' => $category->position,
                    'children' => $build($category->id),
                ])->values();
        };

        return $build($parentId)->toArray();
    }
}
