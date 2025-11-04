<?php

namespace App\Http\Controllers;

use App\Models\InventoryCount;
use App\Models\Product;
use App\Services\Warehouse\InventoryCountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryCountController extends Controller
{
    public function __construct(private InventoryCountService $service)
    {
        $this->middleware('auth');
        $this->authorizeResource(InventoryCount::class, 'inventory_count');
    }

    /**
     * Display a listing of inventory counts
     */
    public function index(Request $request): Response
    {
        $paginator = $request->user()
            ->inventoryCounts()
            ->with(['warehouse', 'countedBy', 'approvedBy'])
            ->withCount(['items', 'itemsWithDiscrepancies'])
            ->when($request->string('status')->isNotEmpty(), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->when($request->string('warehouse_id')->isNotEmpty(), function ($query) use ($request) {
                $query->where('warehouse_location_id', $request->integer('warehouse_id'));
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $warehouses = $request->user()->warehouseLocations()->orderBy('name')->get();

        $statusOptions = collect(\App\Enums\InventoryCountStatus::cases())->map(fn($status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ]);

        return Inertia::render('Warehouse/Inventory/Index', [
            'inventoryCounts' => $paginator->through(fn($count) => [
                'id' => $count->id,
                'name' => $count->name,
                'description' => $count->description,
                'status' => $count->status->value,
                'status_label' => $count->status->label(),
                'status_color' => $count->status->color(),
                'warehouse_name' => $count->warehouse->name,
                'started_at' => $count->started_at?->format('Y-m-d H:i'),
                'completed_at' => $count->completed_at?->format('Y-m-d H:i'),
                'counted_by' => $count->countedBy?->name,
                'approved_by' => $count->approvedBy?->name,
                'total_products' => $count->items_count,
                'total_discrepancies' => $count->items_with_discrepancies_count,
                'created_at' => $count->created_at->format('Y-m-d H:i'),
            ]),
            'warehouses' => $warehouses->map(fn($warehouse) => [
                'value' => (string) $warehouse->id,
                'label' => $warehouse->name,
            ]),
            'statusOptions' => $statusOptions,
            'filters' => [
                'status' => $request->string('status')->toString(),
                'warehouse_id' => $request->string('warehouse_id')->toString(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new inventory count
     */
    public function create(Request $request): Response
    {
        $warehouses = $request->user()->warehouseLocations()->orderBy('name')->get();

        return Inertia::render('Warehouse/Inventory/Create', [
            'warehouses' => $warehouses->map(fn($warehouse) => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
            ]),
        ]);
    }

    /**
     * Store a newly created inventory count
     */
    public function store(Request $request): RedirectResponse
    {
        $inventoryCount = $this->service->create($request->user(), $request->all());

        return redirect()
            ->route('inventory-counts.show', $inventoryCount)
            ->with('status', 'Inwentaryzacja została utworzona.');
    }

    /**
     * Display the specified inventory count
     */
    public function show(InventoryCount $inventory_count): Response
    {
        $inventory_count->load([
            'warehouse',
            'countedBy',
            'approvedBy',
            'items.product',
        ]);

        $items = $inventory_count->items->map(fn($item) => [
            'id' => $item->id,
            'product' => [
                'id' => $item->product->id,
                'name' => $item->product->name,
                'sku' => $item->product->sku,
                'ean' => $item->product->ean,
            ],
            'system_quantity' => (float) $item->system_quantity,
            'counted_quantity' => (float) $item->counted_quantity,
            'quantity_difference' => $item->quantity_difference,
            'unit_cost' => (float) $item->unit_cost,
            'value_difference' => $item->value_difference,
            'discrepancy_type' => $item->discrepancy_type,
            'notes' => $item->notes,
            'counted_at' => $item->counted_at?->format('Y-m-d H:i'),
        ]);

        return Inertia::render('Warehouse/Inventory/Show', [
            'inventoryCount' => [
                'id' => $inventory_count->id,
                'name' => $inventory_count->name,
                'description' => $inventory_count->description,
                'status' => $inventory_count->status->value,
                'status_label' => $inventory_count->status->label(),
                'status_color' => $inventory_count->status->color(),
                'warehouse_name' => $inventory_count->warehouse->name,
                'started_at' => $inventory_count->started_at?->format('Y-m-d H:i'),
                'completed_at' => $inventory_count->completed_at?->format('Y-m-d H:i'),
                'counted_by' => $inventory_count->countedBy?->name,
                'approved_by' => $inventory_count->approvedBy?->name,
                'total_products_counted' => $inventory_count->total_products_counted,
                'total_discrepancies' => $inventory_count->total_discrepancies,
                'total_discrepancy_value' => $inventory_count->total_discrepancy_value,
                'can_be_started' => $inventory_count->status->canBeStarted(),
                'can_be_completed' => $inventory_count->canBeCompleted(),
                'can_be_approved' => $inventory_count->canBeApproved(),
                'can_be_cancelled' => $inventory_count->status->canBeCancelled(),
                'allows_editing' => $inventory_count->status->allowsEditing(),
                'created_at' => $inventory_count->created_at->format('Y-m-d H:i'),
            ],
            'items' => $items,
        ]);
    }

    /**
     * Show the form for editing the specified inventory count
     */
    public function edit(InventoryCount $inventory_count): Response
    {
        $warehouses = $inventory_count->user->warehouseLocations()->orderBy('name')->get();

        return Inertia::render('Warehouse/Inventory/Edit', [
            'inventoryCount' => [
                'id' => $inventory_count->id,
                'name' => $inventory_count->name,
                'description' => $inventory_count->description,
                'warehouse_location_id' => $inventory_count->warehouse_location_id,
                'status' => $inventory_count->status->value,
                'status_label' => $inventory_count->status->label(),
                'allows_editing' => $inventory_count->status->allowsEditing(),
            ],
            'warehouses' => $warehouses->map(fn($warehouse) => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
            ]),
        ]);
    }

    /**
     * Update the specified inventory count
     */
    public function update(Request $request, InventoryCount $inventory_count): RedirectResponse
    {
        $this->service->update($inventory_count, $request->all());

        return redirect()
            ->route('inventory-counts.show', $inventory_count)
            ->with('status', 'Inwentaryzacja została zaktualizowana.');
    }

    /**
     * Remove the specified inventory count from storage
     */
    public function destroy(InventoryCount $inventory_count): RedirectResponse
    {
        if (!$inventory_count->status->allowsDeletion()) {
            return back()->with('error', 'Nie można usunąć inwentaryzacji w statusie: ' . $inventory_count->status->label());
        }

        $inventory_count->delete();

        return redirect()
            ->route('inventory-counts.index')
            ->with('status', 'Inwentaryzacja została usunięta.');
    }

    /**
     * Start inventory count
     */
    public function start(InventoryCount $inventory_count, Request $request): RedirectResponse
    {
        try {
            $this->service->start($inventory_count, $request->user());
            return back()->with('status', 'Inwentaryzacja została rozpoczęta.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Complete inventory count
     */
    public function complete(InventoryCount $inventory_count): RedirectResponse
    {
        try {
            $this->service->complete($inventory_count);
            return back()->with('status', 'Inwentaryzacja została zakończona.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Approve inventory count
     */
    public function approve(InventoryCount $inventory_count, Request $request): RedirectResponse
    {
        try {
            $this->service->approve($inventory_count, $request->user());
            return back()->with('status', 'Inwentaryzacja została zatwierdzona i utworzono dokumenty korygujące.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Cancel inventory count
     */
    public function cancel(InventoryCount $inventory_count): RedirectResponse
    {
        try {
            $this->service->cancel($inventory_count);
            return back()->with('status', 'Inwentaryzacja została anulowana.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Find product by EAN code (API endpoint for scanner)
     */
    public function findProductByEan(Request $request)
    {
        $request->validate([
            'ean' => 'required|string',
        ]);

        $product = Product::where('ean', $request->string('ean'))->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produkt o kodzie EAN ' . $request->string('ean') . ' nie został znaleziony.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'ean' => $product->ean,
            ],
        ]);
    }

    /**
     * Update counted quantity for a product (API endpoint for scanner)
     */
    public function updateQuantity(InventoryCount $inventory_count, Request $request)
    {
        $request->validate([
            'product_id' => 'sometimes|integer|exists:products,id',
            'ean' => 'sometimes|string',
            'counted_quantity' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            // Find product by ID or EAN
            $productId = $request->integer('product_id');
            if (!$productId && $request->has('ean')) {
                $product = Product::where('ean', $request->string('ean'))->first();
                if (!$product) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Produkt o kodzie EAN ' . $request->string('ean') . ' nie został znaleziony.',
                    ], 404);
                }
                $productId = $product->id;
            }

            if (!$productId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Musisz podać product_id lub ean.',
                ], 400);
            }

            $item = $this->service->updateCountedQuantity(
                $inventory_count,
                $productId,
                $request->float('counted_quantity'),
                $request->string('ean', '')->toString() ?: null,
                $request->string('notes', '')->toString() ?: null
            );

            return response()->json([
                'success' => true,
                'item' => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                        'ean' => $item->product->ean,
                    ],
                    'system_quantity' => (float) $item->system_quantity,
                    'counted_quantity' => (float) $item->counted_quantity,
                    'quantity_difference' => $item->quantity_difference,
                    'unit_cost' => (float) $item->unit_cost,
                    'value_difference' => $item->value_difference,
                    'discrepancy_type' => $item->discrepancy_type,
                    'notes' => $item->notes,
                    'counted_at' => $item->counted_at?->format('Y-m-d H:i'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Mark all uncounted items as zero
     */
    public function zeroUncounted(InventoryCount $inventory_count, Request $request)
    {
        try {
            $items = $this->service->markUncountedAsZero(
                $inventory_count,
                $request->boolean('include_missing', false)
            );

            return response()->json([
                'success' => true,
                'items' => $items->map(fn ($item) => [
                    'id' => $item->id,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                        'ean' => $item->product->ean,
                    ],
                    'system_quantity' => (float) $item->system_quantity,
                    'counted_quantity' => (float) $item->counted_quantity,
                    'quantity_difference' => $item->quantity_difference,
                    'unit_cost' => (float) $item->unit_cost,
                    'value_difference' => $item->value_difference,
                    'discrepancy_type' => $item->discrepancy_type,
                    'notes' => $item->notes,
                    'counted_at' => $item->counted_at?->format('Y-m-d H:i'),
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
