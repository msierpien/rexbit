<?php

namespace App\Http\Controllers;

use App\Enums\WarehouseDocumentStatus;
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
                'status' => $document->status->value,
                'status_label' => $document->status->label(),
                'status_badge_class' => $document->status->badgeClass(),
                'can_be_edited' => $document->canBeEdited(),
                'can_be_deleted' => $document->canBeDeleted(),
                'deletion_block_reason' => $document->getDeletionBlockReason(),
                'available_transitions' => $document->getAvailableTransitions(),
            ]);

        return Inertia::render('Warehouse/Documents/Index', [
            'documents' => $documents,
        ]);
    }

    public function create(Request $request): Response
    {
        $products = $request->user()->products()
            ->with('warehouseStocks.warehouse')
            ->orderBy('name')
            ->get();
        $warehouses = $request->user()->warehouseLocations()->orderBy('name')->get();
        $contractors = $request->user()->contractors()->orderBy('name')->get();

        return Inertia::render('Warehouse/Documents/Create', [
            'products' => $products->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'ean' => $product->ean,
                'warehouse_stocks' => $product->warehouseStocks->map(fn ($stock) => [
                    'warehouse_location_id' => $stock->warehouse_location_id,
                    'warehouse_name' => $stock->warehouse->name ?? 'N/A',
                    'on_hand' => $stock->on_hand,
                    'reserved' => $stock->reserved,
                    'incoming' => $stock->incoming,
                    'available' => $stock->on_hand - $stock->reserved,
                ])->values(),
            ])->values(),
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
        // Check if document can be edited
        if (!$warehouse_document->canBeEdited()) {
            return redirect()->route('warehouse.documents.index')
                ->with('error', 'Nie można edytować zatwierdzonego dokumentu.');
        }

        $products = $request->user()->products()
            ->with('warehouseStocks.warehouse')
            ->orderBy('name')
            ->get();
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
            'products' => $products->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'ean' => $product->ean,
                'warehouse_stocks' => $product->warehouseStocks->map(fn ($stock) => [
                    'warehouse_location_id' => $stock->warehouse_location_id,
                    'warehouse_name' => $stock->warehouse->name ?? 'N/A',
                    'on_hand' => $stock->on_hand,
                    'reserved' => $stock->reserved,
                    'incoming' => $stock->incoming,
                    'available' => $stock->on_hand - $stock->reserved,
                ])->values(),
            ])->values(),
            'warehouses' => $warehouses->map(fn ($warehouse) => $warehouse->only(['id', 'name']))->values(),
            'contractors' => $contractors->map(fn ($contractor) => $contractor->only(['id', 'name']))->values(),
        ]);
    }

    public function update(Request $request, WarehouseDocument $warehouse_document): RedirectResponse
    {
        // Check if document can be edited
        if (!$warehouse_document->canBeEdited()) {
            return redirect()->route('warehouse.documents.edit', $warehouse_document)
                ->with('error', 'Nie można edytować zatwierdzonego dokumentu.');
        }

        $this->service->update($warehouse_document, $request->all());

        return redirect()->route('warehouse.documents.edit', $warehouse_document)->with('status', 'Dokument został zaktualizowany.');
    }

    public function destroy(Request $request, WarehouseDocument $warehouse_document): RedirectResponse
    {
        // Check if document can be deleted
        if (!$warehouse_document->canBeDeleted()) {
            return redirect()->route('warehouse.documents.index')
                ->with('error', $warehouse_document->getDeletionBlockReason());
        }

        // Set who deleted the document before soft deleting
        $warehouse_document->deleted_by = $request->user()->id;
        $warehouse_document->save();
        $warehouse_document->delete();

        return redirect()->route('warehouse.documents.index')->with('status', 'Dokument został usunięty.');
    }

    /**
     * Post (approve) a warehouse document
     */
    public function post(Request $request, WarehouseDocument $warehouse_document): RedirectResponse
    {
        try {
            $this->service->post($warehouse_document, $request->user());
            return redirect()->back()->with('status', 'Dokument został zatwierdzony.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Cancel a warehouse document
     */
    public function cancel(Request $request, WarehouseDocument $warehouse_document): RedirectResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500'
        ]);

        try {
            $this->service->cancel($warehouse_document, $request->user(), $request->input('reason'));
            return redirect()->back()->with('status', 'Dokument został anulowany.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Archive a warehouse document
     */
    public function archive(Request $request, WarehouseDocument $warehouse_document): RedirectResponse
    {
        try {
            $this->service->archive($warehouse_document, $request->user());
            return redirect()->back()->with('status', 'Dokument został zarchiwizowany.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
