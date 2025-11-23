<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Integration;
use App\Models\OrderItem;
use App\Models\IntegrationProductLink;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\Warehouse\WarehouseDocumentService;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Lista zamówień z filtrowaniem i paginacją
     */
    public function index(Request $request): Response
    {
        $filters = $request->only([
            'search', 'status', 'payment_status', 'integration_id', 
            'date_from', 'date_to', 'sort', 'direction', 'per_page'
        ]);

        $query = Order::query()
            ->with(['integration:id,name,type', 'items:order_id,quantity'])
            ->withTotalItems();

        // 🔍 Wyszukiwanie
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('number', 'ILIKE', "%{$search}%")
                  ->orWhere('external_order_id', 'ILIKE', "%{$search}%")
                  ->orWhere('external_reference', 'ILIKE', "%{$search}%")
                  ->orWhere('customer_name', 'ILIKE', "%{$search}%")
                  ->orWhere('customer_email', 'ILIKE', "%{$search}%");
            });
        }

        // 🏷️ Filtry
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['integration_id'])) {
            if ($filters['integration_id'] === 'manual') {
                $query->whereNull('integration_id');
            } else {
                $query->forIntegration($filters['integration_id']);
            }
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // 📊 Sortowanie
        $sortColumn = $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['direction'] ?? 'desc';

        $allowedSorts = ['number', 'customer_name', 'total_gross', 'status', 'created_at'];
        if (in_array($sortColumn, $allowedSorts)) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->orderByDesc('created_at');
        }

        // 📄 Paginacja
        $perPage = min(max(1, (int)($filters['per_page'] ?? 15)), 100);
        $orders = $query->paginate($perPage)->withQueryString();

        // 🔗 Dostępne integracje dla filtrów (tylko użytkownika)
        $integrations = auth()->user()
            ->integrations()
            ->whereIn('type', ['prestashop', 'prestashop-db'])
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => $filters,
            'integrations' => $integrations
        ]);
    }

    /**
     * Szczegóły zamówienia
     */
    public function show(Order $order): Response
    {
        // 🔐 SECURITY: Global scope zapewnia że użytkownik widzi tylko swoje zamówienia

        $order->load([
            'integration:id,name,type',
            'items.product:id,name,sku,ean',
            'items.integrationProductLink:id,external_product_id',
            'addresses',
            'shippingAddress',
            'billingAddress',
            'statusHistory.changedBy:id,name'
        ]);

        // Breadcrumbs dla nawigacji
        $breadcrumbs = [
            ['name' => 'Dashboard', 'href' => route('dashboard')],
            ['name' => 'Zamówienia', 'href' => route('orders.index')],
            ['name' => "#{$order->number}", 'href' => null]
        ];

        return Inertia::render('Orders/Show', [
            'order' => $order,
            'breadcrumbs' => $breadcrumbs
        ]);
    }

    /**
     * Formularz tworzenia nowego zamówienia
     */
    public function create(): Response
    {
        $integrations = auth()->user()
            ->integrations()
            ->whereIn('type', ['prestashop', 'prestashop-db'])
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return Inertia::render('Orders/Create', [
            'integrations' => $integrations
        ]);
    }

    /**
     * Zapisanie nowego zamówienia
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'integration_id' => 'nullable|exists:integrations,id',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'currency' => 'required|string|size:3',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.name' => 'required|string|max:255',
            'items.*.sku' => 'nullable|string|max:100',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price_gross' => 'required|numeric|min:0'
        ]);

        // 🔐 Sprawdź czy integracja należy do użytkownika
        if ($validated['integration_id']) {
            $integration = auth()->user()->integrations()->find($validated['integration_id']);
            if (!$integration) {
                abort(403, 'Brak dostępu do tej integracji');
            }
        }

        $order = Order::create([
            'user_id' => auth()->id(),
            'integration_id' => $validated['integration_id'],
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $validated['customer_phone'],
            'currency' => $validated['currency'],
            'notes' => $validated['notes'],
            'status' => 'draft',
            'order_date' => now()
        ]);

        // Dodaj pozycje zamówienia
        $totalGross = 0;
        foreach ($validated['items'] as $itemData) {
            $item = $order->items()->create([
                'product_id' => $itemData['product_id'],
                'name' => $itemData['name'],
                'sku' => $itemData['sku'],
                'quantity' => $itemData['quantity'],
                'unit_price_gross' => $itemData['unit_price_gross'],
                'price_gross' => $itemData['quantity'] * $itemData['unit_price_gross']
            ]);
            
            $totalGross += $item->price_gross;
        }

        // Aktualizuj totale
        $order->update(['total_gross' => $totalGross]);

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Zamówienie zostało utworzone');
    }

    /**
     * API: Zmiana statusu zamówienia
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:draft,awaiting_payment,paid,awaiting_fulfillment,picking,ready_for_shipment,shipped,completed,cancelled,returned',
            'comment' => 'nullable|string|max:500'
        ]);

        $success = $order->changeStatus(
            $validated['status'], 
            $validated['comment'] ?? null,
            auth()->user()
        );

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Status zamówienia został zmieniony',
                'order' => $order->fresh()
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Status nie uległ zmianie'
        ]);
    }

    /**
     * API: Aktualizacja pozycji zamówienia
     */
    public function updateItem(Request $request, Order $order, $itemId): JsonResponse
    {
        $item = $order->items()->findOrFail($itemId);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'unit_price_gross' => 'required|numeric|min:0'
        ]);

        $item->update([
            'quantity' => $validated['quantity'],
            'unit_price_gross' => $validated['unit_price_gross'],
            'price_gross' => $validated['quantity'] * $validated['unit_price_gross']
        ]);

        // Przelicz totale zamówienia
        $order->update([
            'total_gross' => $order->items()->sum('price_gross')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pozycja została zaktualizowana',
            'item' => $item,
            'order_total' => $order->fresh()->total_gross
        ]);
    }

    /**
     * API: Pakowanie pozycji zamówienia (skaner) - inkrementuje quantity_shipped
     */
    public function packItem(Request $request, Order $order, $itemId): JsonResponse
    {
        $item = $order->items()->findOrFail($itemId);

        $validated = $request->validate([
            'quantity' => 'nullable|integer|min:1'
        ]);

        $increment = $validated['quantity'] ?? 1;
        $remaining = max(0, $item->quantity - $item->quantity_shipped);

        if ($remaining <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Wszystkie sztuki tej pozycji są już spakowane.'
            ], 422);
        }

        $applied = min($increment, $remaining);
        $item->quantity_shipped += $applied;
        $item->save();

        // Opcjonalnie: ustaw fulfillment_status na picking/ready_for_shipment jeśli wszystko spakowane
        $order->refresh();
        $allPacked = $order->items->every(fn ($row) => $row->quantity_shipped >= $row->quantity);
        if ($allPacked && $order->fulfillment_status !== 'ready_for_shipment') {
            $order->fulfillment_status = 'ready_for_shipment';
            $order->save();
        }

        return response()->json([
            'success' => true,
            'message' => "Spakowano {$applied} szt.",
            'item' => $item->fresh(),
            'order_fulfillment_status' => $order->fulfillment_status,
        ]);
    }

    /**
     * Utwórz rezerwację magazynową z pozycji zamówienia (soft: zapis w meta)
     */
    public function createReservation(Request $request, Order $order): JsonResponse
    {
        if ($order->payment_status !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Rezerwację można utworzyć tylko dla opłaconych zamówień.'
            ], 422);
        }

        $reservation = [
            'created_at' => now()->toISOString(),
            'items' => $order->items->map(fn (OrderItem $item) => [
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'warehouse_location_id' => $item->warehouse_location_id,
            ])->values(),
        ];

        $meta = $order->metadata ?? [];
        $meta['reservation'] = $reservation;
        $order->metadata = $meta;
        $order->fulfillment_status = 'reserved';
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Rezerwacja została utworzona (soft).',
            'reservation' => $reservation,
            'order' => $order->fresh(),
        ]);
    }

    /**
     * Konwersja rezerwacji do dokumentu WZ (tworzy WZ i linkuje w meta)
     */
    public function convertReservationToWz(Request $request, Order $order, WarehouseDocumentService $documentService): JsonResponse
    {
        $order->loadMissing('items');

        if (empty($order->metadata['reservation']['items'])) {
            return response()->json([
                'success' => false,
                'message' => 'Brak rezerwacji do konwersji.'
            ], 422);
        }

        $items = [];
        $warehouseId = null;

        foreach ($order->items as $item) {
            $productId = $item->product_id;
            $itemWarehouse = $item->warehouse_location_id;

            if (!$productId && $item->external_product_id) {
                $link = IntegrationProductLink::query()
                    ->where('integration_id', $order->integration_id)
                    ->where('external_product_id', $item->external_product_id)
                    ->first();
                if ($link) {
                    $productId = $link->product_id;
                    $itemWarehouse = $itemWarehouse ?: $link->warehouse_location_id;
                }
            }

            if (!$productId || !$itemWarehouse) {
                continue;
            }

            $items[] = [
                'product_id' => $productId,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price_net ?? 0,
                'vat_rate' => $item->vat_rate ?? 0,
            ];

            $warehouseId = $warehouseId ?: $itemWarehouse;
        }

        if (empty($items) || !$warehouseId) {
            return response()->json([
                'success' => false,
                'message' => 'Brak mapowania produktów/magazynu do utworzenia WZ.'
            ], 422);
        }

        $document = $documentService->create($request->user(), [
            'warehouse_location_id' => $warehouseId,
            'type' => 'WZ',
            'issued_at' => now()->toDateString(),
            'items' => $items,
        ]);

        $meta = $order->metadata ?? [];
        $meta['reservation_wz'] = [
            'warehouse_document_id' => $document->id,
            'created_at' => now()->toISOString(),
        ];

        $order->metadata = $meta;
        $order->fulfillment_status = 'ready_for_shipment';
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Utworzono WZ z rezerwacji.',
            'document_id' => $document->id,
            'order' => $order->fresh(),
        ]);
    }

    /**
     * Usunięcie zamówienia
     */
    public function destroy(Order $order): RedirectResponse
    {
        // 🔐 Global scope zapewnia bezpieczeństwo
        $orderNumber = $order->number;
        $order->delete();

        return redirect()
            ->route('orders.index')
            ->with('success', "Zamówienie #{$orderNumber} zostało usunięte");
    }

    /**
     * Masowe usuwanie zamówień
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id'
        ]);

        // 🔐 Global scope zapewnia że usuwamy tylko zamówienia użytkownika
        $count = Order::whereIn('id', $validated['order_ids'])->delete();

        return redirect()->route('orders.index')->with('success', "Usunięto {$count} zamówień z lokalnej bazy danych");
    }

    /**
     * Ustawienia zamówień
     */
    public function settings(): Response
    {
        return Inertia::render('Orders/Settings', [
            'integrations' => Integration::select('id', 'name', 'type', 'config')
                ->where('status', 'active')
                ->where('user_id', Auth::id())
                ->get()
                ->map(function ($integration) {
                    return [
                        'id' => $integration->id,
                        'name' => $integration->name,
                        'type' => $integration->type,
                        'order_import_enabled' => $integration->config['order_import_enabled'] ?? false,
                        'create_reservation_on_import' => $integration->config['create_reservation_on_import'] ?? false,
                    ];
                }),
        ]);
    }

    /**
     * Uruchom import zamówień dla konkretnej integracji
     */
    public function runImport(Request $request, Integration $integration): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'integer|min:1|max:200',
            'dry_run' => 'boolean'
        ]);

        try {
            // Sprawdź czy integracja ma włączony import
            if (!($integration->config['order_import_enabled'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import zamówień nie jest włączony dla tej integracji'
                ], 400);
            }

            $limit = $validated['limit'] ?? 50;
            $dryRun = $validated['dry_run'] ?? false;

            // Uruchom komendę importu
            \Illuminate\Support\Facades\Artisan::call('orders:import', [
                'integration' => $integration->id,
                '--limit' => $limit,
                '--dry-run' => $dryRun
            ]);

            $output = \Illuminate\Support\Facades\Artisan::output();
            
            // Parsuj wyniki z output
            preg_match('/Zaimportowano łącznie: (\d+) zamówień/', $output, $importedMatch);
            preg_match('/Błędy: (\d+)/', $output, $errorsMatch);
            
            $imported = isset($importedMatch[1]) ? (int)$importedMatch[1] : 0;
            $errors = isset($errorsMatch[1]) ? (int)$errorsMatch[1] : 0;

            return response()->json([
                'success' => true,
                'imported' => $imported,
                'errors' => $errors,
                'message' => $dryRun 
                    ? "Symulacja: znaleziono {$imported} zamówień do importu"
                    : "Zaimportowano {$imported} zamówień" . ($errors > 0 ? " z {$errors} błędami" : ""),
                'output' => $output
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd podczas importu: ' . $e->getMessage()
            ], 500);
        }
    }
}
