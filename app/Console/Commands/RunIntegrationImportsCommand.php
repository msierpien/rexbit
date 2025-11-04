<?php

namespace App\Console\Commands;

use App\Jobs\ExecuteIntegrationImport;
use App\Models\IntegrationImportProfile;
use App\Services\Integrations\Import\ImportSchedulerService;
use Illuminate\Console\Command;

class RunIntegrationImportsCommand extends Command
{
    protected $signature = 'integrations:run-imports';

    protected $description = 'Wyszukuje profile importów CSV/XML do uruchomienia oraz dodaje je do kolejki.';

    public function __construct(
        protected ImportSchedulerService $scheduler
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = now();

        $profiles = IntegrationImportProfile::query()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->with('integration')
            ->get();

        if ($profiles->isEmpty()) {
            $this->info('Brak profili do uruchomienia.');

            return self::SUCCESS;
        }

        foreach ($profiles as $profile) {
                        ExecuteIntegrationImport::dispatch($profile->id)->onQueue('import');

            $this->scheduler->updateNextRun($profile);

            $this->info(sprintf(
                'Zaplanowano import profilu #%d (%s).',
                $profile->id,
                $profile->name
            ));
        }

        return self::SUCCESS;
    }
}
