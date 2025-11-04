<?php

namespace App\Services\Warehouse;

use App\Enums\InventoryCountStatus;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\Product;
use App\Models\User;
use App\Models\WarehouseStockTotal;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class InventoryCountService
{
    public function __construct(
        private ValidationFactory $validator,
        private DatabaseManager $db,
        private WarehouseDocumentService $documentService,
    ) {
    }

    /**
     * Create a new inventory count
     */
    public function create(User $user, array $attributes): InventoryCount
    {
        $payload = $this->validateCreateData($attributes, $user);

        return $this->db->transaction(function () use ($user, $payload) {
            $inventoryCount = InventoryCount::create([
                'user_id' => $user->id,
                'warehouse_location_id' => $payload['warehouse_location_id'],
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'status' => InventoryCountStatus::DRAFT,
                'metadata' => $payload['metadata'] ?? [],
            ]);

            return $inventoryCount;
        });
    }

    /**
     * Update inventory count
     */
    public function update(InventoryCount $inventoryCount, array $attributes): InventoryCount
    {
        if (!$inventoryCount->status->allowsEditing()) {
            throw new \InvalidArgumentException('Nie można edytować inwentaryzacji w statusie: ' . $inventoryCount->status->label());
        }

        $payload = $this->validateUpdateData($attributes, $inventoryCount);

        return $this->db->transaction(function () use ($inventoryCount, $payload) {
            $inventoryCount->update([
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'metadata' => array_merge($inventoryCount->metadata ?? [], $payload['metadata'] ?? []),
            ]);

            return $inventoryCount->fresh();
        });
    }

    /**
     * Start inventory count (change status from DRAFT to IN_PROGRESS)
     */
    public function start(InventoryCount $inventoryCount, User $user): InventoryCount
    {
        if (!$inventoryCount->status->canBeStarted()) {
            throw new \InvalidArgumentException('Nie można rozpocząć inwentaryzacji w statusie: ' . $inventoryCount->status->label());
        }

        return $this->db->transaction(function () use ($inventoryCount, $user) {
            // Load all products with current stock levels for this warehouse
            $this->loadProductsForCounting($inventoryCount);

            $inventoryCount->update([
                'status' => InventoryCountStatus::IN_PROGRESS,
                'started_at' => Carbon::now(),
                'counted_by' => $user->id,
            ]);

            return $inventoryCount->fresh();
        });
    }

    /**
     * Add or update counted quantity for a product (via scanner or manual input)
     */
    public function updateCountedQuantity(
        InventoryCount $inventoryCount,
        int $productId,
        float $countedQuantity,
        ?string $scannedEan = null,
        ?string $notes = null
    ): InventoryCountItem {
        if (!$inventoryCount->status->allowsEditing()) {
            throw new \InvalidArgumentException('Nie można modyfikować inwentaryzacji w statusie: ' . $inventoryCount->status->label());
        }

        // Verify product belongs to user
        $product = Product::where('id', $productId)
            ->where('user_id', $inventoryCount->user_id)
            ->firstOrFail();

        return $this->db->transaction(function () use ($inventoryCount, $product, $countedQuantity, $scannedEan, $notes) {
            $item = InventoryCountItem::firstOrNew([
                'inventory_count_id' => $inventoryCount->id,
                'product_id' => $product->id,
            ]);

            // If item doesn't exist, get system quantity from current stock
            if (!$item->exists) {
                $stock = WarehouseStockTotal::where('user_id', $inventoryCount->user_id)
                    ->where('product_id', $product->id)
                    ->where('warehouse_location_id', $inventoryCount->warehouse_location_id)
                    ->first();

                $item->system_quantity = $stock?->on_hand ?? 0;
                $item->unit_cost = $product->purchase_price_net ?? 0;
            }

            $item->counted_quantity = $countedQuantity;
            $item->counted_at = Carbon::now();
            $item->scanned_ean = $scannedEan;
            
            if ($notes !== null) {
                $item->notes = $notes;
            }

            $item->save();

            return $item;
        });
    }

    /**
     * Mark all uncounted items as zero (for remaining products)
     *
     * @return Collection<int, InventoryCountItem>
     */
    public function markUncountedAsZero(InventoryCount $inventoryCount, bool $includeMissing = false): Collection
    {
        if (!$inventoryCount->status->allowsEditing()) {
            throw new \InvalidArgumentException('Nie można modyfikować inwentaryzacji w statusie: ' . $inventoryCount->status->label());
        }

        return $this->db->transaction(function () use ($inventoryCount, $includeMissing) {
            $createdItems = collect();

            if ($includeMissing) {
                $createdItems = $this->createMissingZeroItems($inventoryCount);
            }

            $items = $inventoryCount->items()
                ->whereNull('counted_at')
                ->get();

            if ($items->isEmpty()) {
                return $createdItems;
            }

            $now = Carbon::now();

            $updatedItems = $items->map(function (InventoryCountItem $item) use ($now) {
                $item->counted_quantity = 0;
                $item->counted_at = $now;
                $item->save();
                return $item->fresh(['product']);
            });

            return $createdItems->merge($updatedItems);
        });
    }

    /**
     * Create missing items (not yet present in inventory_count_items) with counted quantity 0
     *
     * @return Collection<int, InventoryCountItem>
     */
    private function createMissingZeroItems(InventoryCount $inventoryCount): Collection
    {
        $existingProductIds = InventoryCountItem::query()
            ->where('inventory_count_id', $inventoryCount->id)
            ->pluck('product_id')
            ->all();

        $created = collect();
        $userId = $inventoryCount->user_id;
        $warehouseId = $inventoryCount->warehouse_location_id;
        $now = Carbon::now();

        Product::query()
            ->where('user_id', $userId)
            ->whereNotIn('id', $existingProductIds)
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($inventoryCount, $warehouseId, $userId, $now, &$created) {
                $productIds = $products->pluck('id')->all();

                $stocks = WarehouseStockTotal::query()
                    ->where('user_id', $userId)
                    ->where('warehouse_location_id', $warehouseId)
                    ->whereIn('product_id', $productIds)
                    ->get()
                    ->keyBy('product_id');

                foreach ($products as $product) {
                    $stock = $stocks->get($product->id);

                    $item = InventoryCountItem::create([
                        'inventory_count_id' => $inventoryCount->id,
                        'product_id' => $product->id,
                        'system_quantity' => $stock?->on_hand ?? 0,
                        'counted_quantity' => 0,
                        'unit_cost' => $product->purchase_price_net ?? 0,
                        'counted_at' => $now,
                    ]);

                        $created->push($item->fresh(['product']));
                }
            });

        return $created;
    }

    /**
     * Complete inventory count (change status to COMPLETED)
     */
    public function complete(InventoryCount $inventoryCount): InventoryCount
    {
        if (!$inventoryCount->canBeCompleted()) {
            throw new \InvalidArgumentException('Nie można zakończyć inwentaryzacji w statusie: ' . $inventoryCount->status->label());
        }

        if ($inventoryCount->items()->count() === 0) {
            throw new \InvalidArgumentException('Nie można zakończyć inwentaryzacji bez policzonych produktów.');
        }

        return $this->db->transaction(function () use ($inventoryCount) {
            $inventoryCount->update([
                'status' => InventoryCountStatus::COMPLETED,
                'completed_at' => Carbon::now(),
            ]);

            return $inventoryCount->fresh();
        });
    }

    /**
     * Approve inventory count and create adjustment documents
     */
    public function approve(InventoryCount $inventoryCount, User $approver): InventoryCount
    {
        if (!$inventoryCount->canBeApproved()) {
            throw new \InvalidArgumentException('Nie można zatwierdzić inwentaryzacji w statusie: ' . $inventoryCount->status->label());
        }

        return $this->db->transaction(function () use ($inventoryCount, $approver) {
            // Create adjustment documents for discrepancies
            $this->createAdjustmentDocuments($inventoryCount);

            $inventoryCount->update([
                'status' => InventoryCountStatus::APPROVED,
                'approved_by' => $approver->id,
            ]);

            return $inventoryCount->fresh();
        });
    }

    /**
     * Cancel inventory count
     */
    public function cancel(InventoryCount $inventoryCount): InventoryCount
    {
        if (!$inventoryCount->status->canBeCancelled()) {
            throw new \InvalidArgumentException('Nie można anulować inwentaryzacji w statusie: ' . $inventoryCount->status->label());
        }

        return $this->db->transaction(function () use ($inventoryCount) {
            $inventoryCount->update([
                'status' => InventoryCountStatus::CANCELLED,
            ]);

            return $inventoryCount->fresh();
        });
    }

    /**
     * Load all products for counting (populate inventory_count_items with system quantities)
     */
    private function loadProductsForCounting(InventoryCount $inventoryCount): void
    {
        // Get all products that have stock in this warehouse
        $stocks = WarehouseStockTotal::where('user_id', $inventoryCount->user_id)
            ->where('warehouse_location_id', $inventoryCount->warehouse_location_id)
            ->where('on_hand', '>', 0)
            ->with('product')
            ->get();

        foreach ($stocks as $stock) {
            InventoryCountItem::firstOrCreate(
                [
                    'inventory_count_id' => $inventoryCount->id,
                    'product_id' => $stock->product_id,
                ],
                [
                    'system_quantity' => $stock->on_hand,
                    'counted_quantity' => 0,
                    'unit_cost' => $stock->product->purchase_price_net ?? 0,
                ]
            );
        }
    }

    /**
     * Create adjustment documents for items with discrepancies
     */
    private function createAdjustmentDocuments(InventoryCount $inventoryCount): array
    {
        $discrepancies = $inventoryCount->itemsWithDiscrepancies()->get();
        $documents = [];

        if ($discrepancies->isEmpty()) {
            return $documents;
        }

        // Group by surplus and shortage
        $surpluses = $discrepancies->filter(fn($item) => $item->quantity_difference > 0);
        $shortages = $discrepancies->filter(fn($item) => $item->quantity_difference < 0);

        // Create IN document for surpluses
        if ($surpluses->isNotEmpty()) {
            $documents[] = $this->createAdjustmentDocument($inventoryCount, $surpluses, 'IN', 'Korekta inwentaryzacyjna - nadwyżki');
        }

        // Create OUT document for shortages
        if ($shortages->isNotEmpty()) {
            $documents[] = $this->createAdjustmentDocument($inventoryCount, $shortages, 'OUT', 'Korekta inwentaryzacyjna - niedobory');
        }

        return $documents;
    }

    /**
     * Create single adjustment document
     */
    private function createAdjustmentDocument(
        InventoryCount $inventoryCount,
        $items,
        string $type,
        string $description
    ) {
        $documentItems = $items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'quantity' => abs($item->quantity_difference),
                'unit_price' => $item->unit_cost,
                'vat_rate' => $item->product->purchase_vat_rate ?? 23,
            ];
        })->toArray();

        return $this->documentService->create($inventoryCount->user, [
            'type' => $type,
            'warehouse_location_id' => $inventoryCount->warehouse_location_id,
            'issued_at' => Carbon::now()->toDateString(),
            'metadata' => [
                'source' => 'inventory_count',
                'inventory_count_id' => $inventoryCount->id,
                'description' => $description,
            ],
            'items' => $documentItems,
        ]);
    }

    /**
     * Validate create data
     */
    private function validateCreateData(array $attributes, User $user): array
    {
        $validator = $this->validator->make($attributes, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'warehouse_location_id' => [
                'required',
                'integer',
                Rule::exists('warehouse_locations', 'id')->where('user_id', $user->id),
            ],
            'metadata' => 'nullable|array',
        ]);

        return $validator->validate();
    }

    /**
     * Validate update data
     */
    private function validateUpdateData(array $attributes, InventoryCount $inventoryCount): array
    {
        $validator = $this->validator->make($attributes, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        return $validator->validate();
    }
}
