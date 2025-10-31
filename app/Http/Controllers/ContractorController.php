<?php

namespace App\Http\Controllers;

use App\Models\Contractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Contractor::class, 'contractor');
    }

    public function index(Request $request): View
    {
        $contractors = $request->user()->contractors()->orderBy('name')->paginate(15);

        return view('warehouse.contractors.index', compact('contractors'));
    }

    public function create(): View
    {
        return view('warehouse.contractors.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $contractor = $request->user()->contractors()->create($this->validatePayload($request));

        return redirect()->route('warehouse.contractors.edit', $contractor)->with('status', 'Kontrahent został utworzony.');
    }

    public function edit(Contractor $contractor): View
    {
        return view('warehouse.contractors.edit', compact('contractor'));
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
