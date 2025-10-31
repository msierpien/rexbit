<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Services\Catalog\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function __construct(private CategoryService $service)
    {
        $this->middleware('auth');
        $this->authorizeResource(ProductCategory::class, 'product_category');
    }

    public function index(Request $request): View
    {
        $catalogs = $request->user()->productCatalogs()
            ->with(['categories' => function ($query) {
                $query->whereNull('parent_id')
                    ->with('children')
                    ->orderBy('position');
            }])
            ->orderBy('name')
            ->get();

        return view('catalog.categories.index', compact('catalogs'));
    }

    public function create(Request $request): View
    {
        $catalogs = $request->user()->productCatalogs()->orderBy('name')->get();
        $categories = $request->user()->productCategories()->with('catalog')->orderBy('name')->get();

        return view('catalog.categories.create', compact('catalogs', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->service->create($request->user(), $request->all());

        return redirect()->route('product-categories.index')->with('status', 'Kategoria została utworzona.');
    }

    public function edit(ProductCategory $product_category, Request $request): View
    {
        $catalogs = $request->user()->productCatalogs()->orderBy('name')->get();
        $categories = $request->user()->productCategories()
            ->where('id', '!=', $product_category->id)
            ->with('catalog')
            ->where('catalog_id', $product_category->catalog_id)
            ->orderBy('name')
            ->get();

        return view('catalog.categories.edit', [
            'category' => $product_category,
            'catalogs' => $catalogs,
            'categories' => $categories,
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
}
