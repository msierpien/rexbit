<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IntegrationType;
use App\Http\Controllers\Controller;
use App\Integrations\IntegrationService;
use App\Models\Integration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
    public function index(Request $request): View
    {
        $type = $request->string('type')->toString() ?: null;
        $types = IntegrationType::cases();

        $integrations = $this->service
            ->list($request->user(), $type);

        return view('dashboard.admin.integrations.index', compact('integrations', 'types', 'type'));
    }

    /**
     * Show the form for creating a new integration.
     */
    public function create(): View
    {
        $types = IntegrationType::cases();

        return view('dashboard.admin.integrations.create', compact('types'));
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
    public function edit(Integration $integration): View
    {
        $integration->load([
            'user.productCatalogs' => fn ($query) => $query->orderBy('name'),
            'importProfiles.mappings',
            'importProfiles.runs' => fn ($query) => $query->latest()->take(5),
        ]);

        return view('dashboard.admin.integrations.edit', [
            'integration' => $integration,
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
}
