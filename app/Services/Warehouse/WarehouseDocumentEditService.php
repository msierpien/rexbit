<?php

namespace App\Services\Warehouse;

use App\Enums\WarehouseDocumentStatus;
use App\Models\User;
use App\Models\WarehouseDocument;
use App\Models\WarehouseDocumentItem;
use App\Models\WarehouseStockTotal;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;

class WarehouseDocumentEditService
{
    public function __construct(
        private DatabaseManager $db,
    ) {
    }

    /**
     * Edytuj zatwierdzony dokument z automatycznym przeliczeniem stanów magazynowych
     * 
     * @param WarehouseDocument $document
     * @param array $newItems Format: [['product_id' => int, 'quantity' => float, 'unit_price' => float, 'vat_rate' => int], ...]
     * @param User $user
     * @return WarehouseDocument
     * @throws \InvalidArgumentException
     */
    public function editPostedDocument(WarehouseDocument $document, array $newItems, User $user): WarehouseDocument
    {
        if ($document->status !== WarehouseDocumentStatus::POSTED) {
            throw new \InvalidArgumentException('Można edytować tylko zatwierdzone dokumenty.');
        }

        if ($document->user_id !== $user->id) {
            throw new \InvalidArgumentException('Nie możesz edytować dokumentów innych użytkowników.');
        }

        return $this->db->transaction(function () use ($document, $newItems, $user) {
            $document->loadMissing('items');

            // Krok 1: Zapisz obecne pozycje dokumentu
            $oldItems = $document->items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => $item->unit_price,
                    'vat_rate' => $item->vat_rate,
                ];
            })->toArray();

            // Krok 2: Wycofaj obecne ruchy magazynowe
            $this->reverseStockMovements($document, $oldItems);

            // Krok 3: Usuń stare pozycje dokumentu
            $document->items()->delete();

            // Krok 4: Dodaj nowe pozycje dokumentu
            foreach ($newItems as $itemData) {
                WarehouseDocumentItem::create([
                    'warehouse_document_id' => $document->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'] ?? null,
                    'vat_rate' => $itemData['vat_rate'] ?? null,
                    'meta' => $itemData['meta'] ?? null,
                ]);
            }

            // Krok 5: Zastosuj nowe ruchy magazynowe
            $document->refresh();
            $this->applyStockMovements($document, $newItems);

            // Krok 6: Zaloguj edycję w audit trail
            $document->logCustomAction('posted_document_edited', [
                'edited_by' => $user->id,
                'edited_by_name' => $user->name,
                'old_items_count' => count($oldItems),
                'new_items_count' => count($newItems),
                'warehouse_location_id' => $document->warehouse_location_id,
                'document_type' => $document->type,
                'document_number' => $document->number,
            ]);

            Log::info('Zatwierdzony dokument został edytowany', [
                'document_id' => $document->id,
                'document_number' => $document->number,
                'user_id' => $user->id,
                'old_items' => $oldItems,
                'new_items' => $newItems,
            ]);

            return $document->fresh('items');
        });
    }

    /**
     * Wycofaj ruchy magazynowe dla dokumentu
     */
    private function reverseStockMovements(WarehouseDocument $document, array $items): void
    {
        foreach ($items as $item) {
            $stockTotal = WarehouseStockTotal::where([
                'user_id' => $document->user_id,
                'product_id' => $item['product_id'],
                'warehouse_location_id' => $document->warehouse_location_id,
            ])->first();

            if ($stockTotal) {
                $quantityChange = $this->getQuantityChangeForType($document->type, $item['quantity']);
                
                // Odwróć ruch
                $oldStockLevel = $stockTotal->on_hand;
                $stockTotal->on_hand -= $quantityChange;
                $stockTotal->on_hand = max(0, $stockTotal->on_hand);
                $stockTotal->save();

                Log::debug('Stan magazynowy wycofany', [
                    'product_id' => $item['product_id'],
                    'warehouse_location_id' => $document->warehouse_location_id,
                    'old_level' => $oldStockLevel,
                    'change' => -$quantityChange,
                    'new_level' => $stockTotal->on_hand,
                ]);
            }
        }
    }

    /**
     * Zastosuj ruchy magazynowe dla dokumentu
     */
    private function applyStockMovements(WarehouseDocument $document, array $items): void
    {
        foreach ($items as $item) {
            $stockTotal = WarehouseStockTotal::firstOrCreate([
                'user_id' => $document->user_id,
                'product_id' => $item['product_id'],
                'warehouse_location_id' => $document->warehouse_location_id,
            ], [
                'on_hand' => 0,
                'reserved' => 0,
                'incoming' => 0,
            ]);

            $quantityChange = $this->getQuantityChangeForType($document->type, $item['quantity']);
            
            $oldStockLevel = $stockTotal->on_hand;
            $stockTotal->on_hand += $quantityChange;
            $stockTotal->save();

            Log::debug('Stan magazynowy zaktualizowany', [
                'product_id' => $item['product_id'],
                'warehouse_location_id' => $document->warehouse_location_id,
                'old_level' => $oldStockLevel,
                'change' => $quantityChange,
                'new_level' => $stockTotal->on_hand,
            ]);
        }
    }

    /**
     * Oblicz zmianę ilości na podstawie typu dokumentu
     */
    private function getQuantityChangeForType(string $documentType, float $quantity): float
    {
        // PZ (przyjęcie zewnętrzne) i IN (przyjęcie wewnętrzne) = dodatnie
        // WZ (wydanie zewnętrzne) i OUT (wydanie wewnętrzne) = ujemne
        return in_array($documentType, ['PZ', 'IN']) ? $quantity : -$quantity;
    }

    /**
     * Podgląd zmian w stanach magazynowych przed zapisaniem
     * 
     * @param WarehouseDocument $document
     * @param array $newItems
     * @return array
     */
    public function previewStockChanges(WarehouseDocument $document, array $newItems): array
    {
        $document->loadMissing('items');

        $changes = [];

        // Zbierz wszystkie produkty (stare i nowe)
        $allProductIds = collect($document->items)
            ->pluck('product_id')
            ->merge(collect($newItems)->pluck('product_id'))
            ->unique()
            ->values()
            ->all();

        foreach ($allProductIds as $productId) {
            $oldItem = $document->items->firstWhere('product_id', $productId);
            $newItem = collect($newItems)->firstWhere('product_id', $productId);

            $oldQuantity = $oldItem ? (float) $oldItem->quantity : 0;
            $newQuantity = $newItem ? (float) $newItem['quantity'] : 0;

            $oldChange = $this->getQuantityChangeForType($document->type, $oldQuantity);
            $newChange = $this->getQuantityChangeForType($document->type, $newQuantity);
            $netChange = $newChange - $oldChange;

            if ($netChange != 0 || $oldQuantity != $newQuantity) {
                // Pobierz obecny stan magazynowy
                $stockTotal = WarehouseStockTotal::where([
                    'user_id' => $document->user_id,
                    'product_id' => $productId,
                    'warehouse_location_id' => $document->warehouse_location_id,
                ])->first();

                $currentStock = $stockTotal ? $stockTotal->on_hand : 0;

                $changes[] = [
                    'product_id' => $productId,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $newQuantity,
                    'old_stock_change' => $oldChange,
                    'new_stock_change' => $newChange,
                    'net_stock_change' => $netChange,
                    'current_stock' => $currentStock,
                    'new_stock' => $currentStock + $netChange,
                ];
            }
        }

        return $changes;
    }
}
