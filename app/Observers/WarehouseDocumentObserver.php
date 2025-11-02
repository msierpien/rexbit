<?php

namespace App\Observers;

use App\Models\WarehouseDocument;

class WarehouseDocumentObserver
{
    /**
     * Handle the WarehouseDocument "deleted" event.
     */
    public function deleted(WarehouseDocument $warehouseDocument): void
    {
        // Only reverse stock movements if document is in POSTED state and wasn't cancelled before
        // This prevents double reversing when deleting a cancelled document
        if ($warehouseDocument->status->value === 'posted') {
            $warehouseDocument->reverseStockMovements();
        }
    }
}