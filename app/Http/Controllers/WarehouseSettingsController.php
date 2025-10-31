<?php

namespace App\Http\Controllers;

use App\Models\WarehouseDocumentSetting;
use App\Models\WarehouseLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private array $documentTypes = ['PZ', 'WZ', 'IN', 'OUT'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $warehouses = $user->warehouseLocations()
            ->with('catalogs')
            ->orderBy('name')
            ->get()
            ->map(fn (WarehouseLocation $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'is_default' => $location->is_default,
                'strict_control' => $location->strict_control,
                'catalogs' => $location->catalogs->map(fn ($catalog) => [
                    'id' => $catalog->id,
                    'name' => $catalog->name,
                ]),
            ]);

        $settings = $user->warehouseDocumentSettings()->get()->keyBy('type');

        $documentSettings = collect($this->documentTypes)->mapWithKeys(function (string $type) use ($settings) {
            $setting = $settings->get($type);

            return [
                $type => [
                    'prefix' => $setting?->prefix,
                    'suffix' => $setting?->suffix,
                    'next_number' => $setting?->next_number ?? 1,
                    'padding' => $setting?->padding ?? 4,
                    'reset_period' => $setting?->reset_period ?? 'none',
                ],
            ];
        });

        $availableCatalogs = $user->productCatalogs()
            ->orderBy('name')
            ->get()
            ->map(fn ($catalog) => $catalog->only(['id', 'name']));

        return Inertia::render('Warehouse/Settings', [
            'warehouses' => $warehouses,
            'document_types' => $this->documentTypes,
            'document_settings' => $documentSettings,
            'catalogs' => $availableCatalogs,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'document_settings' => ['required', 'array'],
            'document_settings.*.prefix' => ['nullable', 'string', 'max:20'],
            'document_settings.*.suffix' => ['nullable', 'string', 'max:20'],
            'document_settings.*.next_number' => ['required', 'integer', 'min:1'],
            'document_settings.*.padding' => ['required', 'integer', 'min:1', 'max:8'],
            'document_settings.*.reset_period' => ['required', 'in:none,daily,monthly,yearly'],
        ]);

        foreach ($data['document_settings'] as $type => $settings) {
            if (! in_array($type, $this->documentTypes, true)) {
                continue;
            }

            $setting = WarehouseDocumentSetting::query()
                ->firstOrCreate([
                    'user_id' => $user->id,
                    'type' => $type,
                ], [
                    'prefix' => $type.'/',
                    'suffix' => null,
                    'next_number' => 1,
                    'padding' => 4,
                    'reset_period' => 'none',
                    'last_reset_at' => now(),
                ]);

            $setting->fill([
                'prefix' => $settings['prefix'] ?? null,
                'suffix' => $settings['suffix'] ?? null,
                'next_number' => $settings['next_number'],
                'padding' => $settings['padding'],
                'reset_period' => $settings['reset_period'],
            ]);

            if ($setting->isDirty('reset_period')) {
                $setting->last_reset_at = null;
            }

            if ($setting->reset_period === 'none') {
                $setting->last_reset_at = $setting->last_reset_at ?? now();
            }

            $setting->save();
        }

        return redirect()->route('warehouse.settings')->with('status', 'Ustawienia dokumentów magazynowych zostały zapisane.');
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('create', WarehouseLocation::class);

        $validated = $this->validateWithBag('location', $request, [
            'location_name' => ['required', 'string', 'max:120'],
            'location_code' => ['nullable', 'string', 'max:30'],
            'location_is_default' => ['nullable', 'boolean'],
            'location_strict_control' => ['nullable', 'boolean'],
            'location_catalogs' => ['nullable', 'array'],
            'location_catalogs.*' => ['integer', 'exists:product_catalogs,id'],
        ]);

        $catalogIds = collect($validated['location_catalogs'] ?? [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($catalogIds->isNotEmpty()) {
            $ownedCatalogIds = $user->productCatalogs()
                ->whereIn('id', $catalogIds)
                ->pluck('id');

            if ($catalogIds->diff($ownedCatalogIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'location_catalogs' => 'Wybrano katalog, do którego nie masz dostępu.',
                ])->errorBag('location');
            }
        }

        $location = $user->warehouseLocations()->create([
            'name' => $validated['location_name'],
            'code' => $validated['location_code'] ?? null,
            'is_default' => (bool) ($validated['location_is_default'] ?? false),
            'strict_control' => (bool) ($validated['location_strict_control'] ?? false),
        ]);

        if ($location->is_default) {
            $user->warehouseLocations()
                ->where('id', '!=', $location->id)
                ->update(['is_default' => false]);
        }

        if ($catalogIds->isNotEmpty()) {
            $location->catalogs()->sync($catalogIds);
        }

        return redirect()
            ->route('warehouse.settings')
            ->with('status', 'Magazyn został dodany.');
    }
}
