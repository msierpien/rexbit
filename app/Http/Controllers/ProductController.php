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

        $query = $request->user()->products()->with(['category.catalog', 'catalog', 'manufacturer']);

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
                'slug' => $product->slug,
                'sku' => $product->sku,
                'ean' => $product->ean,
                'description' => $product->description,
                'catalog_id' => $product->catalog_id,
                'category_id' => $product->category_id,
                'manufacturer_id' => $product->manufacturer_id,
                'status' => $product->status?->value,
                'status_label' => Str::title($product->status?->value ?? 'unknown'),
                'catalog' => $product->catalog?->only(['id', 'name']),
                'category' => $product->category
                    ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                        'catalog_name' => $product->category->catalog?->name,
                    ]
                    : null,
                'manufacturer' => $product->manufacturer?->only(['id', 'name']),
                'purchase_price_net' => $product->purchase_price_net,
                'purchase_vat_rate' => $product->purchase_vat_rate,
                'sale_price_net' => $product->sale_price_net,
                'sale_vat_rate' => $product->sale_vat_rate,
                'images' => $product->images,
                'updated_at' => $product->updated_at?->toDateTimeString(),
            ]);

        return Inertia::render('Products/Index', [
            'products' => [
                'data' => $products->items(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'from' => $products->firstItem(),
                    'last_page' => $products->lastPage(),
                    'links' => $products->toArray()['links'],
                    'path' => $products->path(),
                    'per_page' => $products->perPage(),
                    'to' => $products->lastItem(),
                    'total' => $products->total(),
                ],
            ],
            'filters' => $filters,
            'options' => [
                'catalogs' => $request->user()->productCatalogs()
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($catalog) => $catalog->only(['id', 'name']))
                    ->values(),
                'categories' => $request->user()->productCategories()
                    ->with('catalog:id,name')
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($category) => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'catalog_id' => $category->catalog_id,
                        'catalog_name' => $category->catalog?->name,
                    ])
                    ->values(),
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

        if ($request->expectsJson()) {
            return response()->json([
                'catalogs' => $catalogs,
                'categories' => $categories,
                'manufacturers' => $manufacturers,
            ]);
        }

        abort(404);
    }

    public function store(Request $request)
    {
        $product = $this->service->create($request->user(), $request->all());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Produkt został utworzony.',
                'product' => $product->load(['catalog', 'category', 'manufacturer']),
            ]);
        }

        return redirect()
            ->route('products.index')
            ->with('status', 'Produkt został utworzony.')
            ->setStatusCode(303);
    }

    public function edit(Product $product, Request $request)
    {
        $catalogs = $request->user()->productCatalogs()->orderBy('name')->get();
        $categories = $request->user()->productCategories()->with('catalog')->orderBy('name')->get();
        $manufacturers = $request->user()->manufacturers()->orderBy('name')->get();

        if ($request->expectsJson()) {
            return response()->json([
                'product' => $product,
                'catalogs' => $catalogs,
                'categories' => $categories,
                'manufacturers' => $manufacturers,
            ]);
        }

        abort(404);
    }

    public function update(Request $request, Product $product)
    {
        $updatedProduct = $this->service->update($product, $request->all());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Produkt został zaktualizowany.',
                'product' => $updatedProduct->load(['catalog', 'category', 'manufacturer']),
            ]);
        }

        return redirect()
            ->route('products.index')
            ->with('status', 'Produkt został zaktualizowany.')
            ->setStatusCode(303);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->service->delete($product);

        return redirect()->route('products.index')->with('status', 'Produkt został usunięty.');
    }
}
