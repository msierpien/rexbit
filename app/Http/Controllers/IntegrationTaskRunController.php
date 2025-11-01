<?php

namespace App\Http\Controllers;

use App\Models\IntegrationTaskRun;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationTaskRunController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        $runs = $request->user()
            ->integrationTaskRuns()
            ->with(['task:id,name,integration_id', 'task.integration:id,name'])
            ->latest('integration_task_runs.created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (IntegrationTaskRun $run) => [
                'id' => $run->id,
                'task_name' => $run->task->name,
                'integration_name' => $run->task->integration->name,
                'status' => $run->status,
                'processed_count' => $run->processed_count,
                'success_count' => $run->success_count,
                'failure_count' => $run->failure_count,
                'message' => $run->message,
                'started_at' => $run->started_at?->format('Y-m-d H:i:s'),
                'finished_at' => $run->finished_at?->format('Y-m-d H:i:s'),
                'created_at' => $run->created_at->format('Y-m-d H:i:s'),
            ]);

        return Inertia::render('TaskRuns/Index', [
            'runs' => $runs,
        ]);
    }

    public function show(Request $request, IntegrationTaskRun $run): Response
    {
        // Check if user owns this run
        if ($run->task->integration->user_id !== $request->user()->id) {
            abort(403);
        }

        $run->load(['task:id,name,integration_id', 'task.integration:id,name']);

        return Inertia::render('TaskRuns/Show', [
            'run' => [
                'id' => $run->id,
                'task_name' => $run->task->name,
                'integration_name' => $run->task->integration->name,
                'status' => $run->status,
                'processed_count' => $run->processed_count,
                'success_count' => $run->success_count,
                'failure_count' => $run->failure_count,
                'message' => $run->message,
                'started_at' => $run->started_at?->format('Y-m-d H:i:s'),
                'finished_at' => $run->finished_at?->format('Y-m-d H:i:s'),
                'created_at' => $run->created_at->format('Y-m-d H:i:s'),
                'log' => $run->meta['log'] ?? [],
                'meta' => $run->meta ?? [],
            ],
        ]);
    }
}