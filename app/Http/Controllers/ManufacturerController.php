<?php

namespace App\Http\Controllers;

use App\Models\Manufacturer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ManufacturerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Manufacturer::class, 'manufacturer');
    }

    public function index(Request $request): Response
    {
        $manufacturers = $request->user()->manufacturers()
            ->withCount('products')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Manufacturer $manufacturer) => [
                'id' => $manufacturer->id,
                'name' => $manufacturer->name,
                'slug' => $manufacturer->slug,
                'website' => $manufacturer->website,
                'products_count' => $manufacturer->products_count,
            ]);

        return Inertia::render('Products/Manufacturers/Index', [
            'manufacturers' => $manufacturers,
            'can' => [
                'create' => $request->user()->can('create', Manufacturer::class),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Products/Manufacturers/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $manufacturer = $request->user()->manufacturers()->create($this->validatePayload($request));

        return redirect()->route('manufacturers.edit', $manufacturer)->with('status', 'Producent został utworzony.');
    }

    public function edit(Manufacturer $manufacturer): Response
    {
        return Inertia::render('Products/Manufacturers/Edit', [
            'manufacturer' => [
                'id' => $manufacturer->id,
                'name' => $manufacturer->name,
                'slug' => $manufacturer->slug,
                'website' => $manufacturer->website,
                'contacts' => $manufacturer->contacts ? json_encode($manufacturer->contacts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null,
            ],
        ]);
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
