<?php

namespace App\Services\Warehouse;

use App\Models\Product;
use App\Models\User;
use App\Models\WarehouseDocument;
use App\Models\WarehouseDocumentItem;
use App\Models\WarehouseLocation;
use App\Services\Warehouse\WarehouseStockService;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;

class WarehouseDocumentService
{
    public function __construct(
        private ValidationFactory $validator,
        private DatabaseManager $db,
        private WarehouseStockService $stockService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, array $attributes): WarehouseDocument
    {
        $payload = $this->validate($attributes, null, $user);

        return $this->db->transaction(function () use ($user, $payload) {
            $document = new WarehouseDocument(Arr::except($payload, ['items']));
            $document->user()->associate($user);
            $document->save();

            $this->syncItems($document, $payload['items'] ?? []);

            return $document->fresh('items');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(WarehouseDocument $document, array $attributes): WarehouseDocument
    {
        $payload = $this->validate($attributes, $document);

        return $this->db->transaction(function () use ($document, $payload) {
            $document->fill(Arr::except($payload, ['items']))->save();

            if (isset($payload['items'])) {
                $this->syncItems($document, $payload['items']);
            }

            return $document->fresh('items');
        });
    }

    public function post(WarehouseDocument $document): WarehouseDocument
    {
        if ($document->status === 'posted') {
            return $document;
        }

        return $this->db->transaction(function () use ($document) {
            $document->loadMissing('items');

            foreach ($document->items as $item) {
                $this->stockService->applyMovement($document, $item);
            }

            $document->forceFill(['status' => 'posted'])->save();

            return $document->fresh('items');
        });
    }

    protected function syncItems(WarehouseDocument $document, array $items): void
    {
        $document->items()->delete();

        $records = collect($items)->map(function ($item) use ($document) {
            $product = Product::query()
                ->where('user_id', $document->user_id)
                ->findOrFail($item['product_id']);

            return new WarehouseDocumentItem([
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'] ?? null,
                'vat_rate' => $item['vat_rate'] ?? null,
                'meta' => $item['meta'] ?? null,
            ]);
        });

        $document->items()->saveMany($records);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function validate(array $attributes, ?WarehouseDocument $document = null, ?User $user = null): array
    {
        $rules = [
            'warehouse_location_id' => ['nullable', 'exists:warehouse_locations,id'],
            'contractor_id' => ['nullable', 'exists:contractors,id'],
            'type' => ['required', 'string', 'max:10'],
            'number' => ['required', 'string', 'max:50'],
            'issued_at' => ['required', 'date'],
            'metadata' => ['nullable', 'array'],
            'status' => ['nullable', 'in:draft,posted'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric'],
            'items.*.unit_price' => ['nullable', 'numeric'],
            'items.*.vat_rate' => ['nullable', 'integer'],
        ];

        $validated = $this->validator->make($attributes, $rules)->validate();

        $owner = $document?->getAttribute('user') ?? $user;

        if (! $owner && $document) {
            $owner = $document->user()->first();
        }

        if ($owner instanceof User) {
            if (isset($validated['warehouse_location_id']) && ! $owner->warehouseLocations()->where('id', $validated['warehouse_location_id'])->exists()) {
                throw new \InvalidArgumentException('Wybrany magazyn jest nieprawidłowy.');
            }

            if (isset($validated['contractor_id']) && $validated['contractor_id'] !== null && ! $owner->contractors()->where('id', $validated['contractor_id'])->exists()) {
                throw new \InvalidArgumentException('Wybrany kontrahent jest nieprawidłowy.');
            }
        }

        return $validated;
    }
}
