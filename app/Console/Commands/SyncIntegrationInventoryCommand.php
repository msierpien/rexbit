<?php

namespace App\Console\Commands;

use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Services\Integrations\IntegrationInventorySyncService;
use Illuminate\Console\Command;

class SyncIntegrationInventoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integrations:sync-inventory 
                            {--integration= : Specific integration ID to sync}
                            {--force : Force sync even if interval has not passed}
                            {--dry-run : Show what would be synced without actually syncing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize inventory between local warehouse and external integrations (Prestashop)';

    /**
     * Execute the console command.
     */
    public function handle(IntegrationInventorySyncService $syncService): int
    {
        $integrationId = $this->option('integration');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Running in DRY RUN mode - no changes will be made');
        }

        $this->info('Starting inventory synchronization...');

        $query = Integration::where('type', IntegrationType::PRESTASHOP->value);

        if ($integrationId) {
            $query->where('id', $integrationId);
        }

        $integrations = $query->get();

        if ($integrations->isEmpty()) {
            $this->error('No integrations found');
            return Command::FAILURE;
        }

        $this->info("Found {$integrations->count()} integration(s) to process");

        $stats = [
            'total_integrations' => 0,
            'synced_integrations' => 0,
            'skipped_integrations' => 0,
            'total_products_synced' => 0,
            'errors' => 0,
        ];

        foreach ($integrations as $integration) {
            $stats['total_integrations']++;

            $this->line('');
            $this->info("Processing integration: {$integration->name} (ID: {$integration->id})");

            $mode = $syncService->getSyncMode($integration);

            if ($mode === 'disabled') {
                $this->warn('  ↳ Skipped: Sync mode is disabled');
                $stats['skipped_integrations']++;
                continue;
            }

            if (!$force && !$syncService->shouldRunScheduled($integration)) {
                $interval = $syncService->getSyncIntervalMinutes($integration);
                $this->warn("  ↳ Skipped: Last sync was less than {$interval} minutes ago");
                $stats['skipped_integrations']++;
                continue;
            }

            if ($dryRun) {
                $linksCount = $integration->productLinks()
                    ->whereNotNull('external_product_id')
                    ->count();
                $this->info("  ↳ Would sync {$linksCount} product link(s) in mode: {$mode}");
                $stats['synced_integrations']++;
                continue;
            }

            try {
                $result = $syncService->syncIntegration($integration);
                
                $this->info("  ↳ Synced {$result['synced']} product(s) in mode: {$mode}");
                $stats['synced_integrations']++;
                $stats['total_products_synced'] += $result['synced'];
            } catch (\Exception $e) {
                $this->error("  ↳ Error: {$e->getMessage()}");
                $stats['errors']++;
            }
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('Synchronization Summary:');
        $this->info('═══════════════════════════════════════');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total integrations', $stats['total_integrations']],
                ['Synced integrations', $stats['synced_integrations']],
                ['Skipped integrations', $stats['skipped_integrations']],
                ['Total products synced', $stats['total_products_synced']],
                ['Errors', $stats['errors']],
            ]
        );

        if ($dryRun) {
            $this->warn('This was a DRY RUN - no actual changes were made');
        }

        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
