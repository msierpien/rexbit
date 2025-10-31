<?php

namespace App\Http\Controllers;

use App\Models\WarehouseDocument;
use App\Services\Warehouse\WarehouseDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseDocumentController extends Controller
{
    public function __construct(private WarehouseDocumentService $service)
    {
        $this->middleware('auth');
        $this->authorizeResource(WarehouseDocument::class, 'warehouse_document');
    }

    public function index(Request $request): View
    {
        $documents = $request->user()->warehouseDocuments()->with(['warehouse', 'contractor'])->latest('issued_at')->paginate(15);

        return view('warehouse.documents.index', compact('documents'));
    }

    public function create(Request $request): View
    {
        $products = $request->user()->products()->orderBy('name')->get();
        $warehouses = $request->user()->warehouseLocations()->orderBy('name')->get();
        $contractors = $request->user()->contractors()->orderBy('name')->get();

        return view('warehouse.documents.create', compact('products', 'warehouses', 'contractors'));
    }

    public function store(Request $request): RedirectResponse
    {
        $document = $this->service->create($request->user(), $request->all());

        return redirect()->route('warehouse.documents.edit', $document)->with('status', 'Dokument został utworzony.');
    }

    public function edit(WarehouseDocument $warehouse_document, Request $request): View
    {
        $products = $request->user()->products()->orderBy('name')->get();
        $warehouses = $request->user()->warehouseLocations()->orderBy('name')->get();
        $contractors = $request->user()->contractors()->orderBy('name')->get();

        return view('warehouse.documents.edit', [
            'document' => $warehouse_document->load('items.product'),
            'products' => $products,
            'warehouses' => $warehouses,
            'contractors' => $contractors,
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
