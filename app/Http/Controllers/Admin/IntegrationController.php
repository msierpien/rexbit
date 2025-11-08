<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IntegrationStatus;
use App\Enums\IntegrationType;
use App\Http\Controllers\Controller;
use App\Integrations\IntegrationService;
use App\Models\Contractor;
use App\Models\Integration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function __construct(
        protected IntegrationService $service,
    ) {
        $this->middleware('auth');
        $this->authorizeResource(Integration::class, 'integration');
    }

    /**
     * Display a listing of the integrations.
     */
    public function index(Request $request): Response
    {
        $type = $request->string('type')->toString() ?: null;
        $types = collect(IntegrationType::cases());
        $activeType = $type ? IntegrationType::tryFrom($type) : null;

        $integrations = $this->service
            ->list($request->user(), $activeType);

        $presented = $integrations->map(fn (Integration $integration) => $this->presentIntegration($integration));

        $stats = $this->integrationStats($request->user()->integrations()->get());

        return Inertia::render('Integrations/Index', [
            'integrations' => $presented,
            'filters' => [
                'type' => $activeType?->value,
            ],
            'types' => $types->map(fn (IntegrationType $integrationType) => $this->typeMetadata($integrationType)),
            'stats' => $stats,
            'can' => [
                'create' => $request->user()->can('create', Integration::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new integration.
     */
    public function create(Request $request): Response
    {
        $types = collect(IntegrationType::cases());
        $defaultType = $request->string('type')->toString();
        $selectedType = IntegrationType::tryFrom($defaultType) ?? $types->first();

        $warehouses = $request->user()?->warehouseLocations()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($warehouse) => $warehouse->only(['id', 'name']))
            ->values() ?? collect();

        return Inertia::render('Integrations/Create', [
            'types' => $types
                ->map(fn (IntegrationType $type) => array_merge(
                    $this->typeMetadata($type),
                    ['fields' => $this->driverFields($type, false, ['warehouses' => $warehouses])]
                )),
            'defaults' => [
                'type' => $selectedType?->value,
                'config' => $types
                    ->mapWithKeys(fn (IntegrationType $type) => [
                        $type->value => $this->driverDefaultConfig($type),
                    ]),
            ],
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * Store a newly created integration in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'type' => ['required', Rule::enum(IntegrationType::class)],
        ]);

        $type = IntegrationType::from($request->string('type')->toString());

        $driver = $this->service->driver($type);

        $validated = $request->validate(
            $driver->validationRules()
        );

        $integration = $this->service->create(
            $request->user(),
            $type,
            $validated
        );

        return redirect()
            ->route('integrations.edit', $integration)
            ->with('status', 'Integracja została utworzona.');
    }

    /**
     * Show the form for editing the specified integration.
     */
    public function edit(Request $request, Integration $integration): Response
    {
        $integration->load([
            'user.productCatalogs' => fn ($query) => $query->orderBy('name'),
            'tasks',
            'tasks.runs' => fn ($query) => $query->latest()->take(5),
        ]);

        $catalogs = $integration->user
            ? $integration->user->productCatalogs->sortBy('name')->map(
                fn ($catalog) => $catalog->only(['id', 'name'])
            )->values()
            : collect();

        $profiles = $integration->type === IntegrationType::CSV_XML_IMPORT
            ? $integration->tasks->map(fn ($task) => [
                'id' => $task->id,
                'name' => $task->name,
                'task_type' => $task->task_type ?? 'import',
                'resource_type' => $task->resource_type ?? 'products',
                'format' => $task->format,
                'source_type' => $task->source_type,
                'source_location' => $task->source_location,
                'catalog_id' => $task->catalog_id,
                'delimiter' => $task->delimiter,
                'has_header' => (bool) $task->has_header,
                'is_active' => (bool) $task->is_active,
                'fetch_mode' => $task->fetch_mode,
                'fetch_interval_minutes' => $task->fetch_interval_minutes,
                'fetch_daily_at' => optional($task->fetch_daily_at)->format('H:i'),
                'fetch_cron_expression' => $task->fetch_cron_expression,
                'options' => $task->options ?? [],
                'last_headers' => $task->last_headers ?? [],
                'next_run_at' => optional($task->next_run_at)->toDateTimeString(),
                'next_run_human' => optional($task->next_run_at)?->diffForHumans(),
                'runs' => $task->runs->map(fn ($run) => [
                    'id' => $run->id,
                    'status' => $run->status,
                    'status_variant' => $this->statusVariant($run->status),
                    'created_at' => $run->created_at?->toDateTimeString(),
                    'created_at_human' => $run->created_at?->diffForHumans(),
                    'records_imported' => $run->records_imported ?? 0,
                    'records_processed' => $run->records_processed ?? 0,
                    'error_message' => $run->error_message,
                ]),
                'mappings' => $this->transformMappingsForFrontend($task->mappings ?? []),
            ])->values()
            : collect();

        $warehouses = $integration->user
            ? $integration->user->warehouseLocations()->orderBy('name')->get()->map(
                fn ($warehouse) => $warehouse->only(['id', 'name'])
            )->values()
            : collect();

        $suppliers = $integration->user
            ? $integration->user
                ->contractors()
                ->where('is_supplier', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($supplier) => $supplier->only(['id', 'name']))
                ->values()
            : collect();

        return Inertia::render('Integrations/Edit', [
            'integration' => array_merge(
                $this->presentIntegration($integration),
                [
                    'description' => Arr::get($integration->config, 'description'),
                    'config' => [
                        'base_url' => Arr::get($integration->config, 'base_url'),
                        'description' => Arr::get($integration->config, 'description'),
                        'product_listing_enabled' => Arr::get($integration->config, 'product_listing_enabled', false),
                        'inventory_sync_mode' => Arr::get($integration->config, 'inventory_sync_mode', 'disabled'),
                        'primary_warehouse_id' => Arr::get($integration->config, 'primary_warehouse_id'),
                        'inventory_sync_interval_minutes' => Arr::get(
                            $integration->config,
                            'inventory_sync_interval_minutes',
                            180
                        ),
                    ],
                    'timestamps' => [
                        'created_at' => $integration->created_at?->toDateTimeString(),
                        'created_at_human' => $integration->created_at?->diffForHumans(),
                        'updated_at' => $integration->updated_at?->toDateTimeString(),
                        'updated_at_human' => $integration->updated_at?->diffForHumans(),
                    ],
                ]
            ),
            'driver_fields' => $this->driverFields($integration->type, true, ['warehouses' => $warehouses]),
            'supports_import_profiles' => $integration->type === IntegrationType::CSV_XML_IMPORT,
            'profiles' => $profiles->all(),
            'profile_meta' => [
                'product_fields' => $this->productMappingFields(),
                'category_fields' => $this->categoryMappingFields(),
                'supplier_fields' => $this->supplierAvailabilityMappingFields(),
            ],
            'catalogs' => $catalogs->all(),
            'warehouses' => $warehouses->all(),
            'suppliers' => $suppliers->all(),
            'can' => [
                'delete' => $request->user()?->can('delete', $integration) ?? false,
                'update' => $request->user()?->can('update', $integration) ?? false,
            ],
        ]);
    }

    /**
     * Update the specified integration in storage.
     */
    public function update(Request $request, Integration $integration): RedirectResponse
    {
        $driver = $this->service->driver($integration->type);

        $validated = $request->validate(
            $driver->validationRules($integration)
        );

        $this->service->update($integration, $validated);

        return redirect()
            ->route('integrations.edit', $integration)
            ->with('status', 'Integracja została zaktualizowana.');
    }

    /**
     * Remove the specified integration from storage.
     */
    public function destroy(Request $request, Integration $integration): RedirectResponse
    {
        $integration->delete();

        return redirect()
            ->route('integrations.index')
            ->with('status', 'Integracja została usunięta.');
    }

    /**
     * Test integration configuration.
     */
    public function test(Integration $integration): RedirectResponse
    {
        $this->authorize('update', $integration);

        try {
            $this->service->testConnection($integration);

            return back()->with('status', 'Połączenie z integracją działa poprawnie.');
        } catch (\Throwable $exception) {
            return back()->withErrors([
                'integration' => $exception->getMessage(),
            ]);
        }
    }

    protected function presentIntegration(Integration $integration): array
    {
        return [
            'id' => $integration->id,
            'name' => $integration->name,
            'type' => $integration->type->value,
            'type_label' => $this->typeLabel($integration->type),
            'type_icon' => $this->typeMetadata($integration->type)['icon'] ?? 'plug',
            'status' => $integration->status->value,
            'status_label' => Str::title($integration->status->value),
            'status_variant' => $this->statusVariant($integration->status->value),
            'last_synced_at' => $integration->last_synced_at?->toDateTimeString(),
            'last_synced_human' => $integration->last_synced_at?->diffForHumans(),
            'created_at_human' => $integration->created_at?->diffForHumans(),
        ];
    }

    protected function integrationStats(Collection $integrations): array
    {
        return [
            'total' => $integrations->count(),
            'active' => $integrations->where('status', IntegrationStatus::ACTIVE)->count(),
            'error' => $integrations->where('status', IntegrationStatus::ERROR)->count(),
            'inactive' => $integrations->where('status', IntegrationStatus::INACTIVE)->count(),
        ];
    }

    protected function typeMetadata(IntegrationType $type): array
    {
        $label = $this->typeLabel($type);

        return [
            'value' => $type->value,
            'label' => $label,
            'description' => match ($type) {
                IntegrationType::PRESTASHOP => 'Dwukierunkowa synchronizacja produktów i stanów magazynowych z Prestashop.',
                IntegrationType::CSV_XML_IMPORT => 'Elastyczny import produktów i kategorii z plików CSV/XML lub adresów URL.',
            },
            'icon' => match ($type) {
                IntegrationType::PRESTASHOP => 'store',
                IntegrationType::CSV_XML_IMPORT => 'file-stack',
            },
            'capabilities' => [
                'import_profiles' => $type === IntegrationType::CSV_XML_IMPORT,
            ],
        ];
    }

    protected function typeLabel(IntegrationType $type): string
    {
        return Str::of($type->value)->replace('-', ' ')->title()->toString();
    }

    protected function driverFields(IntegrationType $type, bool $isEdit, array $context = []): array
    {
        return match ($type) {
            IntegrationType::PRESTASHOP => [
                [
                    'name' => 'base_url',
                    'label' => 'Adres podstawowy sklepu (Base URL)',
                    'type' => 'url',
                    'required' => true,
                    'placeholder' => 'https://twoj-sklep.pl',
                ],
                [
                    'name' => 'api_key',
                    'label' => 'Klucz API',
                    'type' => 'password',
                    'required' => ! $isEdit,
                    'placeholder' => $isEdit ? 'Pozostaw puste, aby zachować obecny klucz' : 'Wprowadź klucz API z Prestashop',
                    'helper' => $isEdit
                        ? 'Pozostaw puste, aby zachować obecny klucz API.'
                        : 'Klucz API wygenerowany w panelu Prestashop (Uprawnienia: GET/POST).',
                ],
                [
                    'name' => 'product_listing_enabled',
                    'label' => 'Udostępnij listę produktów',
                    'type' => 'checkbox',
                    'helper' => 'Po zaznaczeniu integracja udostępnia listę produktów w module Prestashop.',
                    'default' => false,
                ],
                [
                    'name' => 'inventory_sync_mode',
                    'label' => 'Synchronizacja stanów magazynowych',
                    'component' => 'select',
                    'default' => 'disabled',
                    'options' => [
                        ['value' => 'disabled', 'label' => 'Wyłączona'],
                        ['value' => 'local_to_presta', 'label' => 'Lokalny magazyn → PrestaShop'],
                        ['value' => 'prestashop_to_local', 'label' => 'PrestaShop → lokalny magazyn'],
                    ],
                    'helper' => 'Wybierz kierunek synchronizacji stanów magazynowych.',
                ],
                [
                    'name' => 'primary_warehouse_id',
                    'label' => 'Główny magazyn (dla synchronizacji lokalnej)',
                    'component' => 'select',
                    'options' => collect($context['warehouses'] ?? [])->map(function ($warehouse) {
                        $id = $warehouse['id'] ?? $warehouse->id ?? null;
                        $name = $warehouse['name'] ?? $warehouse->name ?? null;

                        if ($id === null) {
                            return null;
                        }

                        return [
                            'value' => (string) $id,
                            'label' => $name ?? 'Magazyn',
                        ];
                    })->filter()->values(),
                    'helper' => 'Wymagane, jeśli źródłem prawdy jest lokalny magazyn.',
                ],
                [
                    'name' => 'inventory_sync_interval_minutes',
                    'label' => 'Interwał synchronizacji (minuty)',
                    'type' => 'number',
                    'default' => 180,
                    'min' => 5,
                    'helper' => 'Minimalny interwał 5 minut. Domyślnie 180 (3 godziny).',
                ],
            ],
            IntegrationType::CSV_XML_IMPORT => [],
        };
    }

    protected function driverDefaultConfig(IntegrationType $type): array
    {
        return match ($type) {
            IntegrationType::PRESTASHOP => [
                'base_url' => '',
                'api_key' => '',
                'product_listing_enabled' => false,
                'inventory_sync_mode' => 'disabled',
                'inventory_sync_interval_minutes' => 180,
                'primary_warehouse_id' => null,
            ],
            IntegrationType::CSV_XML_IMPORT => [],
        };
    }

    protected function statusVariant(string $status): string
    {
        return match (strtolower($status)) {
            'active', 'success', 'completed', 'ok' => 'default',
            'error', 'failed', 'failure' => 'destructive',
            'running', 'queued', 'scheduled', 'pending' => 'secondary',
            default => 'secondary',
        };
    }

    protected function productMappingFields(): array
    {
        return [
            'sku' => 'SKU',
            'ean' => 'EAN (kod kreskowy)',
            'name' => 'Nazwa produktu',
            'description' => 'Opis',
            'images' => 'Zdjęcia (URLs oddzielone przecinkami)',
            'sale_price_net' => 'Cena sprzedaży netto',
            'sale_vat_rate' => 'VAT sprzedaży (%)',
            'purchase_price_net' => 'Cena zakupu netto',
            'purchase_vat_rate' => 'VAT zakupu (%)',
            'category_slug' => 'Slug kategorii',
            'category_name' => 'Nazwa kategorii',
        ];
    }

    protected function categoryMappingFields(): array
    {
        return [
            'slug' => 'Slug kategorii',
            'name' => 'Nazwa kategorii',
            'parent_slug' => 'Slug kategorii nadrzędnej',
            'parent_name' => 'Nazwa kategorii nadrzędnej',
        ];
    }

    protected function supplierAvailabilityMappingFields(): array
    {
        return [
            'supplier_sku' => 'Kod produktu u dostawcy',
            'sku' => 'SKU w systemie',
            'ean' => 'EAN (opcjonalnie)',
            'stock_quantity' => 'Stan magazynowy u dostawcy',
            'is_available' => 'Flaga dostępności (1/0, true/false)',
            'delivery_days' => 'Czas dostawy w dniach',
            'purchase_price' => 'Cena zakupu netto',
            'available_later' => 'Etykieta „dostępny później”',
        ];
    }

    /**
     * Transform mappings array to frontend format
     */
    protected function transformMappingsForFrontend(array $mappings): array
    {
        $result = [
            'product' => [],
            'category' => [],
            'supplier_availability' => [],
        ];

        foreach ($mappings as $mapping) {
            $targetType = $mapping['target_type'] ?? 'product';
            $targetField = $mapping['target_field'] ?? '';
            $sourceField = $mapping['source_field'] ?? '';

            if (isset($result[$targetType])) {
                $result[$targetType][$targetField] = $sourceField;
            }
        }

        return $result;
    }

    /**
     * Manually trigger inventory sync for PrestaShop integration
     */
    public function syncInventory(Request $request, Integration $integration): RedirectResponse
    {
        if ($integration->type !== IntegrationType::PRESTASHOP) {
            return back()->with('error', 'Synchronizacja stanów magazynowych jest dostępna tylko dla integracji PrestaShop.');
        }

        $this->authorize('update', $integration);

        $productIds = $request->input('product_ids', []);
        
        app(\App\Services\Integrations\IntegrationInventorySyncService::class)
            ->dispatchForIntegration($integration, $productIds);

        $message = empty($productIds) 
            ? 'Synchronizacja wszystkich stanów magazynowych została uruchomiona w tle.'
            : 'Synchronizacja stanów magazynowych dla ' . count($productIds) . ' produktów została uruchomiona w tle.';

        return back()->with('success', $message);
    }
}
