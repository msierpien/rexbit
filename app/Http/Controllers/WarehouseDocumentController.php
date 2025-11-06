<?php

namespace App\Http\Controllers;

use App\Enums\WarehouseDocumentStatus;
use App\Models\WarehouseDocument;
use App\Services\Warehouse\WarehouseDocumentService;
use App\Services\Warehouse\WarehouseDocumentEditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseDocumentController extends Controller
{
    public function __construct(
        private WarehouseDocumentService $service,
        private WarehouseDocumentEditService $editService
    ) {
        $this->middleware('auth');
        $this->authorizeResource(WarehouseDocument::class, 'warehouse_document');
    }

    public function index(Request $request): Response
    {
        $paginator = $request->user()
            ->warehouseDocuments()
            ->select(['warehouse_documents.*'])
            ->with(['warehouse', 'contractor'])
            ->withSum('items as total_quantity', 'quantity')
            ->selectSub(
                'SELECT COALESCE(SUM(quantity * unit_price), 0) FROM warehouse_document_items WHERE warehouse_document_items.warehouse_document_id = warehouse_documents.id',
                'total_net_value'
            )
            ->when($request->string('status')->isNotEmpty(), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->latest('issued_at')
            ->paginate(15);

        $totals = [
            'total_documents' => $paginator->total(),
            'total_quantity' => $paginator->getCollection()->sum(fn ($document) => (float) $document->total_quantity),
            'total_net_value' => $paginator->getCollection()->sum(fn ($document) => (float) $document->total_net_value),
        ];

        $documents = $paginator->through(fn (WarehouseDocument $document) => [
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
            'total_quantity' => (float) $document->total_quantity,
            'total_net_value' => (float) $document->total_net_value,
            'created_at' => $document->created_at?->format('Y-m-d H:i'),
            'metadata' => $document->metadata ?? [],
        ]);

        $statusOptions = collect(WarehouseDocumentStatus::cases())->map(fn ($status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ])->values();

        return Inertia::render('Warehouse/Documents/Index', [
            'documents' => $documents,
            'filters' => [
                'status' => $request->string('status')->toString(),
            ],
            'statusOptions' => $statusOptions,
            'totals' => $totals,
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
                'status' => $warehouse_document->status->value,
                'status_label' => $warehouse_document->status->label(),
                'available_transitions' => $warehouse_document->getAvailableTransitions(),
                'can_be_deleted' => $warehouse_document->canBeDeleted(),
                'deletion_block_reason' => $warehouse_document->getDeletionBlockReason(),
                'warehouse_location_id' => $warehouse_document->warehouse_location_id,
                'contractor_id' => $warehouse_document->contractor_id,
                'issued_at' => $warehouse_document->issued_at?->format('Y-m-d'),
                'metadata' => $warehouse_document->metadata ?? [],
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

    public function show(WarehouseDocument $warehouse_document, Request $request): Response
    {
        $warehouse_document->load([
            'warehouse',
            'contractor',
            'items.product',
            'user',
        ]);

        $this->authorize('view', $warehouse_document);

        $itemTotals = $warehouse_document->items->map(fn ($item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product' => [
                'id' => $item->product?->id,
                'name' => $item->product?->name,
                'sku' => $item->product?->sku,
            ],
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'net_value' => (float) $item->quantity * (float) $item->unit_price,
            'vat_rate' => $item->vat_rate,
        ])->values();

        $summary = [
            'total_items' => $itemTotals->count(),
            'total_quantity' => $itemTotals->sum('quantity'),
            'total_net_value' => $itemTotals->sum('net_value'),
        ];

        $adminPostedEditContext = null;
        if ($request->user()->isAdmin() && $warehouse_document->status === WarehouseDocumentStatus::POSTED) {
            $products = $request->user()->products()
                ->with('warehouseStocks.warehouse')
                ->orderBy('name')
                ->get();

            $adminPostedEditContext = [
                'enabled' => true,
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
            ];
        }

        return Inertia::render('Warehouse/Documents/Show', [
            'document' => [
                'id' => $warehouse_document->id,
                'number' => $warehouse_document->number,
                'type' => $warehouse_document->type,
                'status' => $warehouse_document->status->value,
                'status_label' => $warehouse_document->status->label(),
                'issued_at' => $warehouse_document->issued_at?->format('Y-m-d'),
                'created_at' => $warehouse_document->created_at?->format('Y-m-d H:i'),
                'updated_at' => $warehouse_document->updated_at?->format('Y-m-d H:i'),
                'warehouse_location_id' => $warehouse_document->warehouse_location_id,
                'warehouse' => $warehouse_document->warehouse?->only(['id', 'name']),
                'contractor' => $warehouse_document->contractor?->only(['id', 'name']),
                'user' => $warehouse_document->user?->only(['id', 'name', 'email']),
                'items' => $itemTotals,
                'summary' => $summary,
                'available_transitions' => $warehouse_document->getAvailableTransitions(),
                'can_be_edited' => $warehouse_document->canBeEdited(),
                'can_be_deleted' => $warehouse_document->canBeDeleted(),
                'deletion_block_reason' => $warehouse_document->getDeletionBlockReason(),
                'metadata' => $warehouse_document->metadata ?? [],
            ],
            'adminPostedEdit' => $adminPostedEditContext,
        ]);
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

    /**
     * Edytuj zatwierdzony dokument z przeliczeniem stanów magazynowych (tylko dla adminów)
     */
    public function editPosted(Request $request, WarehouseDocument $warehouse_document): RedirectResponse
    {
        // Sprawdź uprawnienia - tylko admin może edytować zatwierdzone dokumenty
        // Tu możesz dodać własną logikę autoryzacji, np. sprawdzenie roli
        if (!$request->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Tylko administrator może edytować zatwierdzone dokumenty.');
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        try {
            $this->editService->editPostedDocument(
                $warehouse_document,
                $validated['items'],
                $request->user()
            );

            return redirect()
                ->route('warehouse.documents.show', $warehouse_document)
                ->with('status', 'Zatwierdzony dokument został zaktualizowany. Stany magazynowe zostały przeliczone.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Błąd podczas edycji dokumentu: ' . $e->getMessage());
        }
    }

    /**
     * Podgląd zmian w stanach magazynowych przed edycją zatwierdzonego dokumentu
     */
    public function previewPostedEdit(Request $request, WarehouseDocument $warehouse_document)
    {
        // Sprawdź uprawnienia
        if (!$request->user()->isAdmin()) {
            return response()->json(['error' => 'Brak uprawnień'], 403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        try {
            $changes = $this->editService->previewStockChanges(
                $warehouse_document,
                $validated['items']
            );

            return response()->json([
                'success' => true,
                'changes' => $changes,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
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
     * Bulk status update for selected documents
     */
    public function bulkStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['integer', 'exists:warehouse_documents,id'],
            'action' => ['required', 'in:post,cancel,archive'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $documents = WarehouseDocument::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $validated['document_ids'])
            ->get();

        if ($documents->isEmpty()) {
            return redirect()->back()->with('error', 'Nie znaleziono wskazanych dokumentów.');
        }

        $success = 0;
        $failed = [];

        foreach ($documents as $document) {
            try {
                match ($validated['action']) {
                    'post' => $this->service->post($document, $request->user()),
                    'cancel' => $this->service->cancel($document, $request->user(), $validated['reason'] ?? null),
                    'archive' => $this->service->archive($document, $request->user()),
                };

                $success++;
            } catch (\Throwable $exception) {
                $documentLabel = $document->number ?? "#{$document->id}";
                $failed[$documentLabel] = $exception->getMessage();
                report($exception);
            }
        }

        if ($success > 0) {
            $message = "Zmieniono status dla {$success} dokumentów.";
            if (! empty($failed)) {
                $details = collect($failed)
                    ->map(fn ($reason, $label) => "{$label}: {$reason}")
                    ->implode('; ');
                $message .= ' Pomięto: ' . $details . '.';
            }

            return redirect()->back()->with('status', $message);
        }

        $errorDetails = collect($failed)
            ->map(fn ($reason, $label) => "{$label}: {$reason}")
            ->implode('; ');

        $errorMessage = 'Nie udało się zmienić statusu wybranych dokumentów.';
        if ($errorDetails !== '') {
            $errorMessage .= ' Szczegóły: ' . $errorDetails . '.';
        }

        return redirect()->back()->with('error', $errorMessage);
    }
}
