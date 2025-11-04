<?php

namespace App\Http\Controllers;

use App\Jobs\ExecuteIntegrationImport;
use App\Models\Integration;
use App\Models\IntegrationImportProfile;
use App\Services\Integrations\Import\ImportMappingService;
use App\Services\Integrations\Import\ImportProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class IntegrationImportProfileController extends Controller
{
    public function __construct(
        protected ImportProfileService $profiles,
        protected ImportMappingService $mappings,
    ) {
        $this->middleware('auth');
    }

    public function store(Request $request, Integration $integration): RedirectResponse
    {
        $this->authorize('update', $integration);

        $profile = $this->profiles->create($integration, $request->all());

        return redirect()
            ->route('integrations.edit', $integration)
            ->with('status', "Profil importu {$profile->name} został utworzony.");
    }

    public function update(Request $request, Integration $integration, IntegrationImportProfile $profile): RedirectResponse
    {
        $this->authorize('update', $integration);
        $this->ensureRelation($integration, $profile);

        $this->profiles->update($profile, $request->all());

        return redirect()
            ->route('integrations.edit', $integration)
            ->with('status', "Profil importu {$profile->name} został zaktualizowany.");
    }

    public function destroy(Integration $integration, IntegrationImportProfile $profile): RedirectResponse
    {
        $this->authorize('update', $integration);
        $this->ensureRelation($integration, $profile);

        $profile->delete();

        return redirect()
            ->route('integrations.edit', $integration)
            ->with('status', "Profil importu {$profile->name} został usunięty.");
    }

    public function refreshHeaders(Integration $integration, IntegrationImportProfile $profile): RedirectResponse
    {
        $this->authorize('update', $integration);
        $this->ensureRelation($integration, $profile);

        $this->profiles->refreshHeaders($profile);

        return redirect()
            ->route('integrations.edit', $integration)
            ->with('status', "Nagłówki profilu {$profile->name} zostały odświeżone.");
    }

    public function run(Integration $integration, IntegrationImportProfile $profile): RedirectResponse
    {
        $this->authorize('update', $integration);
        $this->ensureRelation($integration, $profile);

                ExecuteIntegrationImport::dispatch($profile->id)->onQueue('import');

        return redirect()
            ->route('integrations.edit', $integration)
            ->with('status', "Import profilu {$profile->name} został zaplanowany.");
    }

    public function mappings(Request $request, Integration $integration, IntegrationImportProfile $profile): RedirectResponse
    {
        $this->authorize('update', $integration);
        $this->ensureRelation($integration, $profile);

        $this->mappings->sync($profile, $request->all());

        return redirect()
            ->route('integrations.edit', $integration)
            ->with('status', "Mapowanie danych dla profilu {$profile->name} zostało zapisane.");
    }

    protected function ensureRelation(Integration $integration, IntegrationImportProfile $profile): void
    {
        if ($profile->integration_id !== $integration->id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
