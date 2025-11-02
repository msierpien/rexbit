<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::log('created', $model, null, $model->getAttributes());
        });

        static::updated(function ($model) {
            if ($model->isDirty()) {
                AuditLog::log('updated', $model, $model->getOriginal(), $model->getChanges());
            }
        });

        static::deleted(function ($model) {
            AuditLog::log('deleted', $model, $model->getAttributes(), null);
        });
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    public function auditStatusChange(string $from, string $to): AuditLog
    {
        return AuditLog::log('status_changed', $this, ['status' => $from], ['status' => $to]);
    }

    public function logCustomAction(string $action, array $data = []): AuditLog
    {
        return AuditLog::log($action, $this, null, $data);
    }
}
