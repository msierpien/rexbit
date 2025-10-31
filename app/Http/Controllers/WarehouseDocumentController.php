<?php

namespace App\Http\Controllers;

use App\Models\WarehouseDocument;
use App\Services\Warehouse\WarehouseDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseDocumentController extends Controller
{
    public function __construct(private WarehouseDocumentService $service)
    {
        $this->middleware('auth');
        $this->authorizeResource(WarehouseDocument::class, 'warehouse_document');
    }

    public function index(Request $request): Response
    {
        $documents = $request->user()
            ->warehouseDocuments()
            ->with(['warehouse', 'contractor'])
            ->latest('issued_at')
            ->paginate(15)
            ->through(fn (WarehouseDocument $document) => [
                'id' => $document->id,
                'number' => $document->number,
                'type' => $document->type,
                'warehouse' => $document->warehouse?->only(['id', 'name']),
                'contractor' => $document->contractor?->only(['id', 'name']),
                'issued_at' => $document->issued_at?->format('Y-m-d'),
                'status' => $document->status,
            ]);

        return Inertia::render('Warehouse/Documents/Index', [
            'documents' => $documents,
        ]);
    }

    public function create(Request $request): Response
    {
        $products = $request->user()->products()->orderBy('name')->get();
        $warehouses = $request->user()->warehouseLocations()->orderBy('name')->get();
        $contractors = $request->user()->contractors()->orderBy('name')->get();

        return Inertia::render('Warehouse/Documents/Create', [
            'products' => $products->map(fn ($product) => $product->only(['id', 'name', 'sku']))->values(),
            'warehouses' => $warehouses->map(fn ($warehouse) => $warehouse->only(['id', 'name']))->values(),
            'contractors' => $contractors->map(fn ($contractor) => $contractor->only(['id', 'name']))->values(),
            'defaults' => [
                'issued_at' => now()->toDateString(),
                'type' => 'PZ',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $document = $this->service->create($request->user(), $request->all());

        return redirect()->route('warehouse.documents.edit', $document)->with('status', 'Dokument został utworzony.');
    }

    public function edit(WarehouseDocument $warehouse_document, Request $request): Response
    {
        $products = $request->user()->products()->orderBy('name')->get();
        $warehouses = $request->user()->warehouseLocations()->orderBy('name')->get();
        $contractors = $request->user()->contractors()->orderBy('name')->get();

        $warehouse_document->load('items.product');

        return Inertia::render('Warehouse/Documents/Edit', [
            'document' => [
                'id' => $warehouse_document->id,
                'number' => $warehouse_document->number,
                'type' => $warehouse_document->type,
                'status' => $warehouse_document->status,
                'warehouse_location_id' => $warehouse_document->warehouse_location_id,
                'contractor_id' => $warehouse_document->contractor_id,
                'issued_at' => $warehouse_document->issued_at?->format('Y-m-d'),
                'items' => $warehouse_document->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'vat_rate' => $item->vat_rate,
                ])->values(),
            ],
            'products' => $products->map(fn ($product) => $product->only(['id', 'name', 'sku']))->values(),
            'warehouses' => $warehouses->map(fn ($warehouse) => $warehouse->only(['id', 'name']))->values(),
            'contractors' => $contractors->map(fn ($contractor) => $contractor->only(['id', 'name']))->values(),
        ]);
    }

    public function update(Request $request, WarehouseDocument $warehouse_document): RedirectResponse
    {
        $this->service->update($warehouse_document, $request->all());

        return redirect()->route('warehouse.documents.edit', $warehouse_document)->with('status', 'Dokument został zaktualizowany.');
    }

    public function destroy(WarehouseDocument $warehouse_document): RedirectResponse
    {
        $warehouse_document->delete();

        return redirect()->route('warehouse.documents.index')->with('status', 'Dokument został usunięty.');
    }
}
