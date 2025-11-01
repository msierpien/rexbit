<?php

namespace App\Http\Controllers;

use App\Jobs\ExecuteIntegrationTask;
use App\Models\Integration;
use App\Models\IntegrationTask;
use App\Services\Integrations\Tasks\TaskService;
use App\Services\Integrations\Tasks\TaskMappingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IntegrationTaskController extends Controller
{
    public function __construct(
        protected TaskService $tasks,
        protected TaskMappingService $mappings,
    ) {
        $this->middleware('auth');
    }

    public function store(Request $request, Integration $integration): RedirectResponse
    {
        $this->authorize('update', $integration);

        $task = $this->tasks->create($integration, $request->all());

        return redirect()
            ->route('integrations.edit', $integration)
            ->with('status', "Zadanie {$task->name} zostało utworzone.");
    }

    public function update(Request $request, Integration $integration, IntegrationTask $task): RedirectResponse
    {
        $this->authorize('update', $integration);
        $this->ensureRelation($integration, $task);

        $this->tasks->update($task, $request->all());

        return redirect()
            ->route('integrations.edit', $integration)
            ->with('status', "Zadanie {$task->name} zostało zaktualizowane.");
    }

    public function destroy(Integration $integration, IntegrationTask $task): RedirectResponse
    {
        $this->authorize('update', $integration);
        $this->ensureRelation($integration, $task);

        $task->delete();

        return redirect()
            ->route('integrations.edit', $integration)
            ->with('status', "Zadanie {$task->name} zostało usunięte.");
    }

    public function refreshHeaders(Integration $integration, IntegrationTask $task): RedirectResponse
    {
        $this->authorize('update', $integration);
        $this->ensureRelation($integration, $task);

        $this->tasks->refreshHeaders($task);

        return redirect()
            ->route('integrations.edit', $integration)
            ->with('status', "Nagłówki zadania {$task->name} zostały odświeżone.");
    }

    public function run(Integration $integration, IntegrationTask $task): RedirectResponse
    {
        $this->authorize('update', $integration);
        $this->ensureRelation($integration, $task);

        ExecuteIntegrationTask::dispatch($task->id);

        return redirect()
            ->route('integrations.edit', $integration)
            ->with('status', "Zadanie {$task->name} zostało zaplanowane.");
    }

    public function saveMappings(Request $request, Integration $integration, IntegrationTask $task): RedirectResponse
    {
        $this->authorize('update', $integration);
        $this->ensureRelation($integration, $task);

        $this->mappings->save($task, $request->input('mappings', []));

        return redirect()
            ->route('integrations.edit', $integration)
            ->with('status', "Mapowanie danych dla zadania {$task->name} zostało zapisane.");
    }

    protected function ensureRelation(Integration $integration, IntegrationTask $task): void
    {
        if ($task->integration_id !== $integration->id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
