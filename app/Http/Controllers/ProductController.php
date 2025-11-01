<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\Catalog\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private ProductService $service)
    {
        $this->middleware('auth');
        $this->authorizeResource(Product::class, 'product');
    }

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'catalog' => $request->integer('catalog'),
            'category' => $request->integer('category'),
            'status' => $request->string('status')->trim()->toString(),
            'view' => $request->string('view')->trim()->toString() ?: 'table',
            'per_page' => (int) $request->integer('per_page', 15),
        ];

        $query = $request->user()->products()->with(['category', 'catalog', 'manufacturer']);

        if ($filters['search']) {
            $query->where(function ($builder) use ($filters): void {
                $builder
                    ->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('sku', 'like', '%'.$filters['search'].'%')
                    ->orWhere('ean', 'like', '%'.$filters['search'].'%');
            });
        }

        if ($filters['catalog']) {
            $query->where('catalog_id', $filters['catalog']);
        }

        if ($filters['category']) {
            $query->where('category_id', $filters['category']);
        }

        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        $products = $query
            ->latest('updated_at')
            ->paginate(max(1, $filters['per_page']))
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'status' => $product->status?->value,
                'status_label' => Str::title($product->status?->value ?? 'unknown'),
                'catalog' => $product->catalog?->only(['id', 'name']),
                'category' => $product->category?->only(['id', 'name']),
                'manufacturer' => $product->manufacturer?->only(['id', 'name']),
                'sale_price_net' => $product->sale_price_net,
                'sale_vat_rate' => $product->sale_vat_rate,
                'updated_at' => $product->updated_at?->toDateTimeString(),
            ]);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => $filters,
            'options' => [
                'catalogs' => $request->user()->productCatalogs()->orderBy('name')->get(['id', 'name']),
                'categories' => $request->user()->productCategories()->orderBy('name')->get(['id', 'name', 'catalog_id']),
                'manufacturers' => $request->user()->manufacturers()->orderBy('name')->get(['id', 'name']),
                'statuses' => collect(ProductStatus::cases())->map(fn ($status) => [
                    'value' => $status->value,
                    'label' => Str::title($status->value),
                ])->values(),
            ],
            'can' => [
                'create' => $request->user()->can('create', Product::class),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $catalogs = $request->user()->productCatalogs()->orderBy('name')->get();
        $categories = $request->user()->productCategories()->with('catalog')->orderBy('name')->get();
        $manufacturers = $request->user()->manufacturers()->orderBy('name')->get();

        // If request expects JSON (for modal), return data
        if ($request->expectsJson()) {
            return response()->json([
                'catalogs' => $catalogs,
                'categories' => $categories,
                'manufacturers' => $manufacturers,
            ]);
        }

        // Otherwise, return Blade view
        return view('catalog.products.create', compact('catalogs', 'categories', 'manufacturers'));
    }

    public function store(Request $request)
    {
        $product = $this->service->create($request->user(), $request->all());

        // If request expects JSON (for modal), return JSON response
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Produkt został utworzony.',
                'product' => $product->load(['catalog', 'category', 'manufacturer']),
            ]);
        }

        // Otherwise, redirect as before
        return redirect()->route('products.edit', $product)->with('status', 'Produkt został utworzony.');
    }

    public function edit(Product $product, Request $request)
    {
        $catalogs = $request->user()->productCatalogs()->orderBy('name')->get();
        $categories = $request->user()->productCategories()->with('catalog')->orderBy('name')->get();
        $manufacturers = $request->user()->manufacturers()->orderBy('name')->get();

        // If request expects JSON (for modal), return data
        if ($request->expectsJson()) {
            return response()->json([
                'product' => $product,
                'catalogs' => $catalogs,
                'categories' => $categories,
                'manufacturers' => $manufacturers,
            ]);
        }

        // Otherwise, return Blade view
        return view('catalog.products.edit', compact('product', 'catalogs', 'categories', 'manufacturers'));
    }

    public function update(Request $request, Product $product)
    {
        $updatedProduct = $this->service->update($product, $request->all());

        // If request expects JSON (for modal), return JSON response
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Produkt został zaktualizowany.',
                'product' => $updatedProduct->load(['catalog', 'category', 'manufacturer']),
            ]);
        }

        // Otherwise, redirect as before
        return redirect()->route('products.edit', $product)->with('status', 'Produkt został zaktualizowany.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->service->delete($product);

        return redirect()->route('products.index')->with('status', 'Produkt został usunięty.');
    }
}
