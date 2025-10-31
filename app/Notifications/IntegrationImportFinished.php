<?php

namespace App\Notifications;

use App\Models\IntegrationImportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class IntegrationImportFinished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ?IntegrationImportRun $run,
        protected bool $success = true,
        protected ?string $errorMessage = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    protected function payload(): array
    {
        return [
            'run_id' => $this->run?->id,
            'profile_id' => $this->run?->profile_id,
            'status' => $this->success ? 'completed' : 'failed',
            'processed' => $this->run?->processed_count,
            'success' => $this->run?->success_count,
            'failure' => $this->run?->failure_count,
            'message' => $this->errorMessage ?? $this->run?->message,
            'meta' => $this->run?->meta,
            'finished_at' => $this->run?->finished_at,
        ];
    }
}
