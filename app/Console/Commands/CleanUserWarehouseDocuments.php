<?php

namespace App\Console\Commands;

use App\Enums\WarehouseDocumentStatus;
use App\Models\User;
use App\Models\WarehouseDocument;
use App\Models\WarehouseStockTotal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanUserWarehouseDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'warehouse:clean-documents 
                            {userId : ID użytkownika}
                            {--dry-run : Tylko podgląd bez faktycznego usuwania}
                            {--force : Wymuś usunięcie bez potwierdzenia}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Czyści wszystkie dokumenty magazynowe użytkownika wraz ze stanami magazynowymi';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->argument('userId');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Sprawdź czy użytkownik istnieje
        $user = User::find($userId);
        if (!$user) {
            $this->error("Użytkownik o ID {$userId} nie istnieje!");
            return 1;
        }

        $this->info("Analizuję dokumenty użytkownika: {$user->name} (ID: {$userId})");
        $this->newLine();

        // Pobierz statystyki dokumentów
        $documents = WarehouseDocument::withTrashed()
            ->where('user_id', $userId)
            ->with('items')
            ->get();

        if ($documents->isEmpty()) {
            $this->info('Brak dokumentów do usunięcia.');
            return 0;
        }

        // Statystyki
        $stats = [
            'total' => $documents->count(),
            'draft' => $documents->where('status', WarehouseDocumentStatus::DRAFT)->count(),
            'posted' => $documents->where('status', WarehouseDocumentStatus::POSTED)->count(),
            'cancelled' => $documents->where('status', WarehouseDocumentStatus::CANCELLED)->count(),
            'archived' => $documents->where('status', WarehouseDocumentStatus::ARCHIVED)->count(),
            'trashed' => $documents->whereNotNull('deleted_at')->count(),
            'total_items' => $documents->sum(fn($doc) => $doc->items->count()),
        ];

        $this->table(
            ['Metryka', 'Wartość'],
            [
                ['Dokumenty ogółem', $stats['total']],
                ['Robocze (draft)', $stats['draft']],
                ['Zatwierdzone (posted)', $stats['posted']],
                ['Anulowane (cancelled)', $stats['cancelled']],
                ['Zarchiwizowane (archived)', $stats['archived']],
                ['Usunięte (soft deleted)', $stats['trashed']],
                ['Pozycje ogółem', $stats['total_items']],
            ]
        );

        $this->newLine();

        // Pobierz stany magazynowe
        $stockTotals = WarehouseStockTotal::where('user_id', $userId)->get();
        
        if ($stockTotals->isNotEmpty()) {
            $this->info("Znalezione stany magazynowe: {$stockTotals->count()} pozycji");
            $totalStock = $stockTotals->sum('on_hand');
            $this->info("Suma stanów magazynowych: {$totalStock}");
            $this->newLine();
        }

        if ($dryRun) {
            $this->warn('TRYB DRY-RUN: Żadne dane nie zostaną usunięte.');
            $this->info('Operacje które zostałyby wykonane:');
            $this->line('1. Usunięcie ' . $stats['total_items'] . ' pozycji dokumentów');
            $this->line('2. Usunięcie ' . $stats['total'] . ' dokumentów magazynowych');
            $this->line('3. Usunięcie ' . $stockTotals->count() . ' stanów magazynowych');
            return 0;
        }

        // Potwierdzenie
        if (!$force) {
            $this->warn('UWAGA: Ta operacja jest nieodwracalna!');
            $this->warn('Zostaną usunięte:');
            $this->warn('- ' . $stats['total'] . ' dokumentów magazynowych');
            $this->warn('- ' . $stats['total_items'] . ' pozycji dokumentów');
            $this->warn('- ' . $stockTotals->count() . ' stanów magazynowych');
            $this->newLine();

            if (!$this->confirm('Czy na pewno chcesz kontynuować?', false)) {
                $this->info('Operacja anulowana.');
                return 0;
            }
        }

        // Wykonaj czyszczenie w transakcji
        try {
            DB::beginTransaction();

            $this->info('Rozpoczynam czyszczenie...');
            $progressBar = $this->output->createProgressBar(3);
            $progressBar->start();

            // Krok 1: Usuń stany magazynowe
            $deletedStocks = WarehouseStockTotal::where('user_id', $userId)->delete();
            $progressBar->advance();

            // Krok 2: Usuń pozycje dokumentów
            $deletedItems = DB::table('warehouse_document_items')
                ->whereIn('warehouse_document_id', $documents->pluck('id'))
                ->delete();
            $progressBar->advance();

            // Krok 3: Usuń dokumenty (najpierw hard delete soft deleted, potem resztę)
            WarehouseDocument::withTrashed()
                ->where('user_id', $userId)
                ->whereNotNull('deleted_at')
                ->forceDelete();

            $deletedDocuments = WarehouseDocument::where('user_id', $userId)->forceDelete();
            $progressBar->advance();

            $progressBar->finish();
            $this->newLine(2);

            DB::commit();

            $this->info('✓ Czyszczenie zakończone pomyślnie!');
            $this->newLine();
            $this->table(
                ['Usunięte elementy', 'Liczba'],
                [
                    ['Dokumenty magazynowe', $stats['total']],
                    ['Pozycje dokumentów', $deletedItems],
                    ['Stany magazynowe', $deletedStocks],
                ]
            );

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Błąd podczas czyszczenia: ' . $e->getMessage());
            $this->error('Transakcja wycofana. Żadne dane nie zostały usunięte.');
            return 1;
        }
    }
}
