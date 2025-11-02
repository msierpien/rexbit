<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Enums\IntegrationType;
use App\Models\Product;
use App\Models\WarehouseDocumentItem;
use App\Models\Integration;
use App\Services\Catalog\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(private ProductService $service)
    {
        $this->middleware('auth');
        $this->authorizeResource(Product::class, 'product');
    }

    public function index(Request $request): Response
    {
        $priceMin = $request->input('price_min');
        $priceMax = $request->input('price_max');
        $sortRaw = $request->string('sort')->trim()->toString();
        $directionRaw = strtolower($request->string('direction', 'asc')->trim()->toString() ?: 'asc');

        $allowedSorts = ['name', 'sku', 'quantity', 'price', 'id'];
        $sort = in_array($sortRaw, $allowedSorts, true) ? $sortRaw : null;
        $direction = in_array($directionRaw, ['asc', 'desc'], true) ? $directionRaw : 'asc';

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'catalog' => $request->integer('catalog'),
            'category' => $request->integer('category'),
            'status' => $request->string('status')->trim()->toString(),
            'view' => $request->string('view')->trim()->toString() ?: 'table',
            'per_page' => (int) $request->integer('per_page', 15),
            'stock' => $request->string('stock')->trim()->toString(),
            'price_min' => is_numeric($priceMin) ? (float) $priceMin : null,
            'price_max' => is_numeric($priceMax) ? (float) $priceMax : null,
            'sort' => $sort,
            'direction' => $direction,
        ];

        $query = $request->user()->products()->with([
            'category.catalog',
            'catalog',
            'manufacturer',
            'warehouseStocks.warehouse',
        ]);
        $stockTotals = DB::table('warehouse_stock_totals')
            ->select('product_id')
            ->selectRaw('COALESCE(SUM(on_hand), 0) as total_on_hand')
            ->selectRaw('COALESCE(SUM(reserved), 0) as total_reserved')
            ->selectRaw('COALESCE(SUM(incoming), 0) as total_incoming')
            ->selectRaw('COALESCE(SUM(on_hand - reserved), 0) as total_available')
            ->groupBy('product_id');

        $query
            ->select(['products.*'])
            ->leftJoinSub($stockTotals, 'stock_totals', function ($join) {
                $join->on('stock_totals.product_id', '=', 'products.id');
            });

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
        if ($filters['stock']) {
            $query->where(function ($builder) use ($filters) {
                if ($filters['stock'] === 'available') {
                    $builder->whereRaw('COALESCE(stock_totals.total_available, 0) > 0');
                } elseif ($filters['stock'] === 'out') {
                    $builder->whereRaw('COALESCE(stock_totals.total_available, 0) <= 0');
                } elseif ($filters['stock'] === 'negative') {
                    $builder->whereRaw('COALESCE(stock_totals.total_available, 0) < 0');
                }
            });
        }

        if ($filters['price_min'] !== null) {
            $query->where(function ($builder) use ($filters): void {
                $builder->whereNotNull('sale_price_net')
                    ->where('sale_price_net', '>=', $filters['price_min']);
            });
        }

        if ($filters['price_max'] !== null) {
            $query->where(function ($builder) use ($filters): void {
                $builder->whereNotNull('sale_price_net')
                    ->where('sale_price_net', '<=', $filters['price_max']);
            });
        }

        if ($sort === 'name') {
            $query->orderBy('products.name', $direction);
        } elseif ($sort === 'sku') {
            $query->orderBy('products.sku', $direction);
        } elseif ($sort === 'price') {
            $query->orderBy('products.sale_price_net', $direction);
        } elseif ($sort === 'id') {
            $query->orderBy('products.id', $direction);
        } elseif ($sort === 'quantity') {
            $query->orderByRaw('COALESCE(stock_totals.total_available, 0) '.$direction);
        } else {
            $query->orderByDesc('products.updated_at');
        }

        $integrationOptions = $request->user()->integrations()
            ->where('type', IntegrationType::PRESTASHOP->value)
            ->orderBy('name')
            ->get()
            ->filter(fn (Integration $integration) => (bool) Arr::get($integration->config, 'product_listing_enabled', false))
            ->map(fn (Integration $integration) => [
                'id' => $integration->id,
                'name' => $integration->name,
                'type' => $integration->type->value,
            ])
            ->values();

        $products = $query
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
                'stock_summary' => [
                    'total_on_hand' => (float) ($product->total_on_hand ?? $product->warehouseStocks->sum('on_hand')),
                    'total_reserved' => (float) ($product->total_reserved ?? $product->warehouseStocks->sum('reserved')),
                    'total_incoming' => (float) ($product->total_incoming ?? $product->warehouseStocks->sum('incoming')),
                    'total_available' => (float) ($product->total_available ?? $product->warehouseStocks
                        ->sum(fn ($stock) => (float) $stock->on_hand - (float) $stock->reserved)),
                ],
                'stocks' => $product->warehouseStocks
                    ->map(fn ($stock) => [
                        'warehouse_id' => $stock->warehouse_location_id,
                        'warehouse_name' => $stock->warehouse?->name ?? '—',
                        'on_hand' => (float) $stock->on_hand,
                        'reserved' => (float) $stock->reserved,
                        'incoming' => (float) $stock->incoming,
                        'available' => (float) $stock->on_hand - (float) $stock->reserved,
                    ])
                    ->values(),
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
            'integrationOptions' => $integrationOptions,
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

    public function stockHistory(Product $product, Request $request)
    {
        $this->authorize('view', $product);

        $product->loadMissing(['warehouseStocks.warehouse']);

        $limit = (int) $request->integer('limit', 50);
        $historyLimit = max(10, min(100, $limit));

        $items = WarehouseDocumentItem::query()
            ->where('product_id', $product->id)
            ->with([
                'document' => function ($query) {
                    $query->withTrashed()->with(['warehouse']);
                },
            ])
            ->orderByDesc('created_at')
            ->limit($historyLimit)
            ->get();

        $history = $items
            ->map(function (WarehouseDocumentItem $item) {
                $document = $item->document;

                if (! $document) {
                    return null;
                }

                $sign = match ($document->type) {
                    'PZ', 'PW', 'IN' => 1,
                    'WZ', 'RW', 'OUT' => -1,
                    default => 0,
                };

                $quantity = (float) $item->quantity;
                $change = $sign * $quantity;

                return [
                    'id' => $item->id,
                    'document_id' => $document->id,
                    'document_number' => $document->number,
                    'document_type' => $document->type,
                    'document_status' => $document->status?->value,
                    'document_status_label' => $document->status?->label(),
                    'issued_at' => $document->issued_at?->format('Y-m-d'),
                    'warehouse_id' => $document->warehouse_location_id,
                    'warehouse_name' => $document->warehouse?->name,
                    'quantity' => $quantity,
                    'quantity_change' => $change,
                    'direction' => $change >= 0 ? 'in' : 'out',
                    'unit_price' => $item->unit_price !== null ? (float) $item->unit_price : null,
                    'created_at' => $item->created_at?->format('Y-m-d H:i'),
                ];
            })
            ->filter()
            ->values();

        $stocks = $product->warehouseStocks
            ->map(fn ($stock) => [
                'warehouse_id' => $stock->warehouse_location_id,
                'warehouse_name' => $stock->warehouse?->name ?? '—',
                'on_hand' => (float) $stock->on_hand,
                'reserved' => (float) $stock->reserved,
                'incoming' => (float) $stock->incoming,
                'available' => (float) $stock->on_hand - (float) $stock->reserved,
            ])
            ->values();

        $summary = [
            'total_on_hand' => (float) $product->warehouseStocks->sum('on_hand'),
            'total_reserved' => (float) $product->warehouseStocks->sum('reserved'),
            'total_incoming' => (float) $product->warehouseStocks->sum('incoming'),
            'total_available' => (float) $product->warehouseStocks
                ->sum(fn ($stock) => (float) $stock->on_hand - (float) $stock->reserved),
        ];

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'ean' => $product->ean,
            ],
            'stocks' => $stocks,
            'history' => $history,
            'summary' => $summary,
        ]);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->service->delete($product);

        return redirect()->route('products.index')->with('status', 'Produkt został usunięty.');
    }
}
