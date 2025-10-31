<?php

namespace App\Http\Controllers;

use App\Models\Contractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContractorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Contractor::class, 'contractor');
    }

    public function index(Request $request): Response
    {
        $contractors = $request->user()->contractors()
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Contractor $contractor) => [
                'id' => $contractor->id,
                'name' => $contractor->name,
                'tax_id' => $contractor->tax_id,
                'email' => $contractor->email,
                'phone' => $contractor->phone,
                'city' => $contractor->city,
                'is_supplier' => $contractor->is_supplier,
                'is_customer' => $contractor->is_customer,
            ]);

        return Inertia::render('Warehouse/Contractors/Index', [
            'contractors' => $contractors,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Warehouse/Contractors/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $contractor = $request->user()->contractors()->create($this->validatePayload($request));

        return redirect()->route('warehouse.contractors.edit', $contractor)->with('status', 'Kontrahent został utworzony.');
    }

    public function edit(Contractor $contractor): Response
    {
        return Inertia::render('Warehouse/Contractors/Edit', [
            'contractor' => [
                'id' => $contractor->id,
                'name' => $contractor->name,
                'tax_id' => $contractor->tax_id,
                'email' => $contractor->email,
                'phone' => $contractor->phone,
                'city' => $contractor->city,
                'street' => $contractor->street,
                'postal_code' => $contractor->postal_code,
                'is_supplier' => $contractor->is_supplier,
                'is_customer' => $contractor->is_customer,
                'meta' => $contractor->meta ? json_encode($contractor->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null,
            ],
        ]);
    }

    public function update(Request $request, Contractor $contractor): RedirectResponse
    {
        $contractor->update($this->validatePayload($request));

        return redirect()->route('warehouse.contractors.edit', $contractor)->with('status', 'Kontrahent został zaktualizowany.');
    }

    public function destroy(Contractor $contractor): RedirectResponse
    {
        $contractor->delete();

        return redirect()->route('warehouse.contractors.index')->with('status', 'Kontrahent został usunięty.');
    }

    protected function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:150'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_supplier' => ['nullable', 'boolean'],
            'is_customer' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
        ]);

        $validated['is_supplier'] = $request->boolean('is_supplier');
        $validated['is_customer'] = $request->boolean('is_customer');

        return $validated;
    }
}
