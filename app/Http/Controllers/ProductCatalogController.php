<?php

namespace App\Http\Controllers;

use App\Models\ProductCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductCatalogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(ProductCatalog::class, 'product_catalog');
    }

    public function index(Request $request): Response
    {
        $catalogs = $request->user()->productCatalogs()
            ->withCount(['products'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ProductCatalog $catalog) => [
                'id' => $catalog->id,
                'name' => $catalog->name,
                'slug' => $catalog->slug,
                'description' => $catalog->description,
                'products_count' => $catalog->products_count,
            ]);

        return Inertia::render('Products/Catalogs/Index', [
            'catalogs' => $catalogs,
            'can' => [
                'create' => $request->user()->can('create', ProductCatalog::class),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Products/Catalogs/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatePayload($request);

        $catalog = $request->user()->productCatalogs()->create($payload);

        return redirect()->route('product-catalogs.edit', $catalog)->with('status', 'Katalog został utworzony.');
    }

    public function edit(ProductCatalog $product_catalog): Response
    {
        return Inertia::render('Products/Catalogs/Edit', [
            'catalog' => [
                'id' => $product_catalog->id,
                'name' => $product_catalog->name,
                'slug' => $product_catalog->slug,
                'description' => $product_catalog->description,
            ],
        ]);
    }

    public function update(Request $request, ProductCatalog $product_catalog): RedirectResponse
    {
        $product_catalog->update($this->validatePayload($request, $product_catalog));

        return redirect()->route('product-catalogs.edit', $product_catalog)->with('status', 'Katalog został zaktualizowany.');
    }

    public function destroy(ProductCatalog $product_catalog): RedirectResponse
    {
        $product_catalog->delete();

        return redirect()->route('product-catalogs.index')->with('status', 'Katalog został usunięty.');
    }

    protected function validatePayload(Request $request, ?ProductCatalog $catalog = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        } else {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        $exists = $request->user()->productCatalogs()
            ->when($catalog, fn ($query) => $query->where('id', '!=', $catalog->id))
            ->where('slug', $validated['slug'])
            ->exists();

        while ($exists) {
            $validated['slug'] .= '-'.Str::random(4);

            $exists = $request->user()->productCatalogs()
                ->when($catalog, fn ($query) => $query->where('id', '!=', $catalog->id))
                ->where('slug', $validated['slug'])
                ->exists();
        }

        return $validated;
    }
}
