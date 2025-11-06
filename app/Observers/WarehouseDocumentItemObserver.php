<?php

namespace App\Observers;

use App\Models\WarehouseDocumentItem;
use Illuminate\Support\Facades\Log;

class WarehouseDocumentItemObserver
{
    /**
     * Handle the WarehouseDocumentItem "creating" event.
     * Zabezpieczenie przed dodawaniem produktów innych użytkowników
     */
    public function creating(WarehouseDocumentItem $item): void
    {
        $this->validateProductOwnership($item);
    }

    /**
     * Handle the WarehouseDocumentItem "updating" event.
     */
    public function updating(WarehouseDocumentItem $item): void
    {
        // Jeśli zmienia się product_id, sprawdź właściciela
        if ($item->isDirty('product_id')) {
            $this->validateProductOwnership($item);
        }
    }

    /**
     * Walidacja właściciela produktu
     */
    protected function validateProductOwnership(WarehouseDocumentItem $item): void
    {
        $item->loadMissing(['document', 'product']);

        if (!$item->document || !$item->product) {
            return; // Będzie walidowane przez foreign keys
        }

        $documentOwnerId = $item->document->user_id;
        $productOwnerId = $item->product->user_id;

        if ($documentOwnerId !== $productOwnerId) {
            Log::warning('Próba dodania produktu innego użytkownika do dokumentu', [
                'document_id' => $item->warehouse_document_id,
                'document_user_id' => $documentOwnerId,
                'product_id' => $item->product_id,
                'product_user_id' => $productOwnerId,
            ]);

            throw new \InvalidArgumentException(
                "Nie możesz dodać produktu (ID: {$item->product_id}) innego użytkownika (User ID: {$productOwnerId}) " .
                "do swojego dokumentu (User ID: {$documentOwnerId}). " .
                "Możesz używać tylko własnych produktów."
            );
        }
    }
}
