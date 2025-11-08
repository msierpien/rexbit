<?php

namespace App\Console\Commands;

use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Services\Integrations\SupplierAvailabilitySyncService;
use Illuminate\Console\Command;

class SyncSupplierAvailabilityCommand extends Command
{
    protected $signature = 'supplier:sync-availability 
        {--prestashop= : ID integracji Prestashop}
        {--contractor= : Opcjonalny ID dostawcy}
        {--limit= : Maksymalna liczba produktów do zsynchronizowania w tym uruchomieniu}';

    protected $description = 'Synchronizuje dostępność u dostawców do sklepów Prestashop.';

    public function __construct(
        protected SupplierAvailabilitySyncService $syncService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $integrationId = (int) $this->option('prestashop');

        if ($integrationId <= 0) {
            $this->error('Podaj poprawne ID integracji Prestashop (--prestashop).');

            return self::FAILURE;
        }

        /** @var Integration|null $integration */
        $integration = Integration::query()
            ->where('type', IntegrationType::PRESTASHOP->value)
            ->find($integrationId);

        if (! $integration) {
            $this->error('Nie znaleziono integracji Prestashop o podanym ID.');

            return self::FAILURE;
        }

        $contractorId = $this->option('contractor') ? (int) $this->option('contractor') : null;
        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : null;

        $this->info(sprintf(
            'Start synchronizacji dostępności (integration_id=%d, contractor=%s, limit=%s)',
            $integration->id,
            $contractorId ? (string) $contractorId : '—',
            $limit ? (string) $limit : '∞'
        ));

        $stats = $this->syncService->syncToPrestashop($integration, $contractorId, [
            'limit' => $limit,
        ]);

        $this->info(sprintf(
            'Zakończono synchronizację. Sukcesy: %d, błędy: %d, łącznie: %d',
            $stats['synced'],
            $stats['failed'],
            $stats['total']
        ));

        if (! empty($stats['errors'])) {
            $this->warn('Lista błędów:');
            foreach (array_slice($stats['errors'], 0, 10) as $error) {
                $this->line(sprintf(
                    ' - Produkt #%d (external #%s): %s',
                    $error['product_id'],
                    $error['external_product_id'],
                    $error['message']
                ));
            }
        }

        return self::SUCCESS;
    }
}
