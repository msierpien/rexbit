<?php

namespace App\Services\Warehouse;

use App\Models\WarehouseDocument;
use App\Models\WarehouseDocumentItem;
use App\Models\WarehouseStockTotal;
use Illuminate\Database\DatabaseManager;

class WarehouseStockService
{
    public function __construct(private DatabaseManager $db)
    {
    }

    public function applyMovement(WarehouseDocument $document, WarehouseDocumentItem $item): void
    {
        $sign = $this->determineSign($document->type);

        if ($sign === 0) {
            return;
        }

        $quantity = $sign * (float) $item->quantity;

        $record = WarehouseStockTotal::query()
            ->firstOrNew([
                'user_id' => $document->user_id,
                'product_id' => $item->product_id,
                'warehouse_location_id' => $document->warehouse_location_id,
            ]);

        $record->on_hand = ($record->on_hand ?? 0) + $quantity;
        $record->save();
    }

    protected function determineSign(string $type): int
    {
        return match ($type) {
            'PZ', 'PW', 'IN' => 1,
            'WZ', 'RW', 'OUT' => -1,
            default => 0,
        };
    }
}
