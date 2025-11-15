<?php

namespace App\Http\Controllers;

use App\Models\OrderStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderStatusController extends Controller
{
    public function index(): Response
    {
        $orderStatuses = OrderStatus::forOrders()->ordered()->get();
        $paymentStatuses = OrderStatus::forPayments()->ordered()->get();

        return Inertia::render('Orders/Statuses/Index', [
            'orderStatuses' => $orderStatuses->map(fn($status) => [
                'id' => $status->id,
                'key' => $status->key,
                'name' => $status->name,
                'color' => $status->color,
                'description' => $status->description,
                'is_default' => $status->is_default,
                'is_final' => $status->is_final,
                'is_system' => $status->is_system,
                'is_active' => $status->is_active,
                'sort_order' => $status->sort_order,
            ]),
            'paymentStatuses' => $paymentStatuses->map(fn($status) => [
                'id' => $status->id,
                'key' => $status->key,
                'name' => $status->name,
                'color' => $status->color,
                'description' => $status->description,
                'is_default' => $status->is_default,
                'is_final' => $status->is_final,
                'is_system' => $status->is_system,
                'is_active' => $status->is_active,
                'sort_order' => $status->sort_order,
            ])
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Orders/Statuses/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'key' => ['required', 'string', 'max:50', 'regex:/^[a-z_]+$/', function ($attribute, $value, $fail) use ($request) {
                if (OrderStatus::where('key', $value)->where('type', $request->type)->exists()) {
                    $fail('Status z tym kluczem już istnieje.');
                }
            }],
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:20',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:order,payment',
            'is_default' => 'boolean',
            'is_final' => 'boolean',
            'sort_order' => 'integer|min:0|max:9999',
        ]);

        // Jeśli to ma być domyślny status, usuń flagę default z innych
        if ($request->is_default) {
            OrderStatus::where('type', $request->type)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $status = OrderStatus::create($request->all());

        return redirect()->route('orders.statuses.index')
            ->with('success', 'Status został dodany.');
    }

    public function edit(OrderStatus $orderStatus): Response
    {
        return Inertia::render('Orders/Statuses/Edit', [
            'status' => [
                'id' => $orderStatus->id,
                'key' => $orderStatus->key,
                'name' => $orderStatus->name,
                'color' => $orderStatus->color,
                'description' => $orderStatus->description,
                'type' => $orderStatus->type,
                'is_default' => $orderStatus->is_default,
                'is_final' => $orderStatus->is_final,
                'is_system' => $orderStatus->is_system,
                'is_active' => $orderStatus->is_active,
                'sort_order' => $orderStatus->sort_order,
            ]
        ]);
    }

    public function update(Request $request, OrderStatus $orderStatus)
    {
        // Nie można edytować klucza statusów systemowych
        $keyRule = $orderStatus->is_system 
            ? 'required|string|max:50'
            : ['required', 'string', 'max:50', 'regex:/^[a-z_]+$/', function ($attribute, $value, $fail) use ($request, $orderStatus) {
                if (OrderStatus::where('key', $value)
                    ->where('type', $request->type)
                    ->where('id', '!=', $orderStatus->id)
                    ->exists()) {
                    $fail('Status z tym kluczem już istnieje.');
                }
            }];

        $request->validate([
            'key' => $keyRule,
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:20',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:order,payment',
            'is_default' => 'boolean',
            'is_final' => 'boolean',
            'sort_order' => 'integer|min:0|max:9999',
            'is_active' => 'boolean',
        ]);

        // Jeśli to ma być domyślny status, usuń flagę default z innych
        if ($request->is_default && !$orderStatus->is_default) {
            OrderStatus::where('type', $request->type)
                ->where('is_default', true)
                ->where('id', '!=', $orderStatus->id)
                ->update(['is_default' => false]);
        }

        $orderStatus->update($request->all());

        return redirect()->route('orders.statuses.index')
            ->with('success', 'Status został zaktualizowany.');
    }

    public function destroy(OrderStatus $orderStatus)
    {
        if ($orderStatus->is_system) {
            return back()->withErrors(['error' => 'Nie można usunąć statusu systemowego.']);
        }

        // Sprawdź czy status nie jest używany
        $ordersCount = \App\Models\Order::where('status', $orderStatus->key)
            ->orWhere('payment_status', $orderStatus->key)
            ->count();

        if ($ordersCount > 0) {
            return back()->withErrors(['error' => "Nie można usunąć statusu używanego przez {$ordersCount} zamówień."]);
        }

        $orderStatus->delete();

        return redirect()->route('orders.statuses.index')
            ->with('success', 'Status został usunięty.');
    }

    public function updateOrder(Request $request)
    {
        $request->validate([
            'statuses' => 'required|array',
            'statuses.*.id' => 'required|exists:order_statuses,id',
            'statuses.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->statuses as $statusData) {
            OrderStatus::where('id', $statusData['id'])
                ->update(['sort_order' => $statusData['sort_order']]);
        }

        return back()->with('success', 'Kolejność statusów została zaktualizowana.');
    }
}