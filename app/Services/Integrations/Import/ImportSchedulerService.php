<?php

namespace App\Services\Integrations\Import;

use App\Models\IntegrationImportProfile;
use Carbon\Carbon;
use Cron\CronExpression;

class ImportSchedulerService
{
    public function computeNextRun(IntegrationImportProfile $profile, ?Carbon $from = null): ?Carbon
    {
        $from = $from ?? now();

        return match ($profile->fetch_mode) {
            'interval' => $this->intervalNextRun($profile, $from),
            'daily' => $this->dailyNextRun($profile, $from),
            'cron' => $this->cronNextRun($profile, $from),
            default => null,
        };
    }

    public function updateNextRun(IntegrationImportProfile $profile): IntegrationImportProfile
    {
        $profile->next_run_at = $profile->is_active ? $this->computeNextRun($profile) : null;
        $profile->save();

        return $profile;
    }

    protected function intervalNextRun(IntegrationImportProfile $profile, Carbon $from): ?Carbon
    {
        $minutes = $profile->fetch_interval_minutes;

        if (! $minutes) {
            return null;
        }

        return $from->copy()->addMinutes($minutes);
    }

    protected function dailyNextRun(IntegrationImportProfile $profile, Carbon $from): ?Carbon
    {
        if (! $profile->fetch_daily_at) {
            return null;
        }

        $next = Carbon::parse($profile->fetch_daily_at, $from->getTimezone())
            ->setDate($from->year, $from->month, $from->day);

        if ($next->lessThanOrEqualTo($from)) {
            $next->addDay();
        }

        return $next;
    }

    protected function cronNextRun(IntegrationImportProfile $profile, Carbon $from): ?Carbon
    {
        if (! $profile->fetch_cron_expression) {
            return null;
        }

        $expression = CronExpression::factory($profile->fetch_cron_expression);

        return Carbon::instance($expression->getNextRunDate($from));
    }
}
