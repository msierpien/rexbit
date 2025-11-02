<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RecalculateWarehouseStocks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'warehouse:recalculate-stocks {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate warehouse stock totals based on posted documents';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
        }
        
        $this->info('Starting warehouse stock recalculation...');
        
        // Step 1: Reset all stock totals to 0
        if (!$dryRun) {
            \App\Models\WarehouseStockTotal::query()->update([
                'on_hand' => 0,
                'reserved' => 0,
                'incoming' => 0,
            ]);
            $this->info('All stock totals reset to 0');
        }
        
        // Step 2: Get all posted warehouse documents ordered by issued_at
        $documents = \App\Models\WarehouseDocument::where('status', 'posted')
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get();
            
        $this->info("Found {$documents->count()} posted documents to process");
        
        $bar = $this->output->createProgressBar($documents->count());
        $bar->start();
        
        $stats = [
            'documents_processed' => 0,
            'stock_movements' => 0,
            'errors' => 0,
        ];
        
        // Step 3: Process each document
        foreach ($documents as $document) {
            try {
                foreach ($document->items as $item) {
                    $stockTotal = \App\Models\WarehouseStockTotal::firstOrCreate([
                        'user_id' => $document->user_id,
                        'product_id' => $item->product_id,
                        'warehouse_location_id' => $document->warehouse_location_id,
                    ], [
                        'on_hand' => 0,
                        'reserved' => 0,
                        'incoming' => 0,
                    ]);
                    
                    // Calculate quantity change based on document type
                    $quantityChange = 0;
                    if (in_array($document->type, ['PZ', 'IN'])) {
                        $quantityChange = $item->quantity;
                    } elseif (in_array($document->type, ['WZ', 'OUT'])) {
                        $quantityChange = -$item->quantity;
                    }
                    
                    if (!$dryRun) {
                        $stockTotal->on_hand += $quantityChange;
                        $stockTotal->save();
                    }
                    
                    $stats['stock_movements']++;
                }
                
                $stats['documents_processed']++;
            } catch (\Exception $e) {
                $this->error("Error processing document {$document->number}: {$e->getMessage()}");
                $stats['errors']++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        // Step 4: Show summary
        $this->info('Recalculation complete!');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Documents processed', $stats['documents_processed']],
                ['Stock movements', $stats['stock_movements']],
                ['Errors', $stats['errors']],
            ]
        );
        
        if ($dryRun) {
            $this->warn('This was a DRY RUN - no changes were made to the database');
            $this->info('Run without --dry-run to apply changes');
        }
        
        return Command::SUCCESS;
    }
}
