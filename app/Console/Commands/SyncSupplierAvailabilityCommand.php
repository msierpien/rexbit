<?php

namespace App\Console\Commands;

use App\Enums\IntegrationType;
use App\Integrations\IntegrationService;
use App\Models\Integration;
use App\Models\IntegrationTask;
use App\Services\Integrations\PrestashopDatabaseSyncService;
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
        protected IntegrationService $integrationService,
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
            ->whereIn('type', [IntegrationType::PRESTASHOP->value, IntegrationType::PRESTASHOP_DB->value])
            ->find($integrationId);

        if (! $integration) {
            $this->error('Nie znaleziono integracji Prestashop o podanym ID.');

            return self::FAILURE;
        }

        $contractorId = $this->option('contractor') ? (int) $this->option('contractor') : null;
        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : null;

        $this->info(sprintf(
            'Start synchronizacji dostępności (integration_id=%d, type=%s, contractor=%s, limit=%s)',
            $integration->id,
            $integration->type->value,
            $contractorId ? (string) $contractorId : '—',
            $limit ? (string) $limit : '∞'
        ));

        // Use database sync for PRESTASHOP_DB type
        if ($integration->type === IntegrationType::PRESTASHOP_DB) {
            $stats = $this->syncUsingDatabase($integration, $limit);
        } else {
            // Use API sync for regular PRESTASHOP type
            $stats = $this->syncService->syncToPrestashop($integration, $contractorId, [
                'limit' => $limit,
            ]);
        }

        $this->info(sprintf(
            'Zakończono synchronizację. Sukcesy: %d, pominięto: %d, błędy: %d, łącznie: %d',
            $stats['synced'] ?? $stats['success'] ?? 0,
            $stats['skipped'] ?? 0,
            $stats['failed'] ?? $stats['errors'] ?? 0,
            $stats['total'] ?? ($stats['success'] + $stats['skipped'] + $stats['errors'])
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

    protected function syncUsingDatabase(Integration $integration, ?int $limit): array
    {
        // Create a temporary task for database sync
        $task = new IntegrationTask([
            'integration_id' => $integration->id,
            'type' => 'manual-sync',
        ]);
        $task->integration = $integration;

        $dbSyncService = new PrestashopDatabaseSyncService($task, $this->integrationService);
        
        return $dbSyncService->syncToPrestashop($limit);
    }
}

