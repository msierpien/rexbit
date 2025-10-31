<?php

namespace App\Http\Controllers;

use App\Models\Manufacturer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ManufacturerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Manufacturer::class, 'manufacturer');
    }

    public function index(Request $request): View
    {
        $manufacturers = $request->user()->manufacturers()->withCount('products')->orderBy('name')->paginate(15);

        return view('catalog.manufacturers.index', compact('manufacturers'));
    }

    public function create(): View
    {
        return view('catalog.manufacturers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $manufacturer = $request->user()->manufacturers()->create($this->validatePayload($request));

        return redirect()->route('manufacturers.edit', $manufacturer)->with('status', 'Producent został utworzony.');
    }

    public function edit(Manufacturer $manufacturer): View
    {
        return view('catalog.manufacturers.edit', compact('manufacturer'));
    }

    public function update(Request $request, Manufacturer $manufacturer): RedirectResponse
    {
        $manufacturer->update($this->validatePayload($request, $manufacturer));

        return redirect()->route('manufacturers.edit', $manufacturer)->with('status', 'Producent został zaktualizowany.');
    }

    public function destroy(Manufacturer $manufacturer): RedirectResponse
    {
        $manufacturer->delete();

        return redirect()->route('manufacturers.index')->with('status', 'Producent został usunięty.');
    }

    protected function validatePayload(Request $request, ?Manufacturer $manufacturer = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url'],
            'contacts' => ['nullable'],
        ]);

        if (isset($validated['contacts']) && is_string($validated['contacts']) && $validated['contacts'] !== '') {
            $decoded = json_decode($validated['contacts'], true);
            $validated['contacts'] = is_array($decoded) ? $decoded : null;
        }

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        $slug = Str::slug($slug);

        $baseQuery = $request->user()->manufacturers()
            ->when($manufacturer, fn ($query) => $query->where('id', '!=', $manufacturer->id));

        while ((clone $baseQuery)->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::random(4);
        }

        $validated['slug'] = $slug;

        return $validated;
    }
}
