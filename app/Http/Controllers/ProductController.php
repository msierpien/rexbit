<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Catalog\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private ProductService $service)
    {
        $this->middleware('auth');
        $this->authorizeResource(Product::class, 'product');
    }

    public function index(Request $request): View
    {
        $products = $request->user()->products()->with(['category', 'catalog', 'manufacturer'])->latest()->paginate(15);

        return view('catalog.products.index', compact('products'));
    }

    public function create(Request $request): View
    {
        $catalogs = $request->user()->productCatalogs()->orderBy('name')->get();
        $categories = $request->user()->productCategories()->with('catalog')->orderBy('name')->get();
        $manufacturers = $request->user()->manufacturers()->orderBy('name')->get();

        return view('catalog.products.create', compact('catalogs', 'categories', 'manufacturers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $product = $this->service->create($request->user(), $request->all());

        return redirect()->route('products.edit', $product)->with('status', 'Produkt został utworzony.');
    }

    public function edit(Product $product, Request $request): View
    {
        $catalogs = $request->user()->productCatalogs()->orderBy('name')->get();
        $categories = $request->user()->productCategories()->with('catalog')->orderBy('name')->get();
        $manufacturers = $request->user()->manufacturers()->orderBy('name')->get();

        return view('catalog.products.edit', compact('product', 'catalogs', 'categories', 'manufacturers'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->service->update($product, $request->all());

        return redirect()->route('products.edit', $product)->with('status', 'Produkt został zaktualizowany.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->service->delete($product);

        return redirect()->route('products.index')->with('status', 'Produkt został usunięty.');
    }
}
