<?php

namespace App\Console\Commands;

use App\Models\IntegrationSyncLog;
use App\Models\IntegrationSyncLogItem;
use Illuminate\Console\Command;

class IntegrationSyncLogsCommand extends Command
{
    protected $signature = 'integrations:sync-logs 
                            {--log= : Specific log ID to view details}
                            {--integration= : Filter by integration ID}
                            {--failed : Show only failed syncs}
                            {--limit=20 : Number of logs to show}';

    protected $description = 'View integration synchronization logs and history';

    public function handle(): int
    {
        if ($logId = $this->option('log')) {
            return $this->showLogDetails($logId);
        }

        return $this->showLogsList();
    }

    protected function showLogsList(): int
    {
        $query = IntegrationSyncLog::with('integration:id,name')
            ->orderBy('created_at', 'desc');

        if ($integrationId = $this->option('integration')) {
            $query->where('integration_id', $integrationId);
        }

        if ($this->option('failed')) {
            $query->whereIn('status', ['failed', 'completed_with_errors']);
        }

        $logs = $query->limit($this->option('limit'))->get();

        if ($logs->isEmpty()) {
            $this->info('No sync logs found.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($logs as $log) {
            $duration = null;
            if ($log->started_at && $log->completed_at) {
                $duration = $log->started_at->diffInSeconds($log->completed_at) . 's';
            }

            $status = $log->status;
            if ($status === 'completed') {
                $status = '<fg=green>✓ completed</>';
            } elseif ($status === 'completed_with_errors') {
                $status = '<fg=yellow>⚠ completed (errors)</>';
            } elseif ($status === 'failed') {
                $status = '<fg=red>✗ failed</>';
            } elseif ($status === 'running') {
                $status = '<fg=blue>⟳ running</>';
            }

            $rows[] = [
                $log->id,
                $log->integration->name ?? 'N/A',
                $log->direction,
                $status,
                $log->total_items,
                "<fg=green>{$log->success_count}</>",
                $log->failed_count > 0 ? "<fg=red>{$log->failed_count}</>" : $log->failed_count,
                $log->skipped_count,
                $duration ?? '-',
                $log->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $this->table(
            ['ID', 'Integration', 'Direction', 'Status', 'Total', 'Success', 'Failed', 'Skipped', 'Duration', 'Started'],
            $rows
        );

        $this->newLine();
        $this->info('Use --log={id} to view details of a specific sync');

        return self::SUCCESS;
    }

    protected function showLogDetails(int $logId): int
    {
        $log = IntegrationSyncLog::with(['integration:id,name', 'user:id,name'])->find($logId);

        if (!$log) {
            $this->error("Sync log #{$logId} not found.");
            return self::FAILURE;
        }

        $this->info("Sync Log #{$log->id}");
        $this->line("Integration: {$log->integration->name}");
        $this->line("Direction: {$log->direction}");
        $this->line("Status: {$log->status}");
        $this->line("Started by: " . ($log->user->name ?? 'System'));
        $this->line("Started at: {$log->started_at}");
        if ($log->completed_at) {
            $duration = $log->started_at->diffInSeconds($log->completed_at);
            $this->line("Completed at: {$log->completed_at} (took {$duration}s)");
        }
        $this->newLine();

        $this->line("Summary:");
        $this->line("  Total items: {$log->total_items}");
        $this->line("  Success: <fg=green>{$log->success_count}</>");
        $this->line("  Failed: <fg=red>{$log->failed_count}</>");
        $this->line("  Skipped: {$log->skipped_count}");
        $this->newLine();

        // Show failed items
        $failedItems = IntegrationSyncLogItem::with('product:id,sku,name')
            ->where('sync_log_id', $logId)
            ->where('status', 'failed')
            ->limit(50)
            ->get();

        if ($failedItems->isNotEmpty()) {
            $this->error("Failed Items ({$failedItems->count()}):");
            
            $rows = [];
            foreach ($failedItems as $item) {
                $rows[] = [
                    $item->product->sku ?? 'N/A',
                    substr($item->product->name ?? 'N/A', 0, 40),
                    $item->external_id,
                    $item->quantity,
                    substr($item->error_message, 0, 60),
                ];
            }

            $this->table(
                ['SKU', 'Product', 'External ID', 'Quantity', 'Error'],
                $rows
            );
        }

        // Show sample success items
        $successItems = IntegrationSyncLogItem::with('product:id,sku,name')
            ->where('sync_log_id', $logId)
            ->where('status', 'success')
            ->limit(10)
            ->get();

        if ($successItems->isNotEmpty()) {
            $this->newLine();
            $this->info("Sample Success Items ({$successItems->count()} of {$log->success_count}):");
            
            $rows = [];
            foreach ($successItems as $item) {
                $rows[] = [
                    $item->product->sku ?? 'N/A',
                    substr($item->product->name ?? 'N/A', 0, 40),
                    $item->external_id,
                    $item->quantity,
                ];
            }

            $this->table(
                ['SKU', 'Product', 'External ID', 'Quantity'],
                $rows
            );
        }

        return self::SUCCESS;
    }
}
