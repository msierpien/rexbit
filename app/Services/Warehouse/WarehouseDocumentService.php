<?php

namespace App\Services\Warehouse;

use App\Models\Product;
use App\Models\User;
use App\Models\WarehouseDocument;
use App\Models\WarehouseDocumentItem;
use App\Models\WarehouseDocumentSetting;
use App\Services\Warehouse\WarehouseStockService;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class WarehouseDocumentService
{
    private array $supportedTypes = ['PZ', 'WZ', 'IN', 'OUT'];

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

        $items = $payload['items'] ?? [];
        unset($payload['items']);

        $type = $payload['type'];

        return $this->db->transaction(function () use ($user, $payload, $items, $type) {
            $data = $payload;

            if (empty($data['number'])) {
                $data['number'] = $this->generateDocumentNumber($user, $type);
            } else {
                $data['number'] = trim($data['number']);
                $this->assertNumberUnique($user, $data['number']);
            }

            $document = new WarehouseDocument($data);
            $document->user()->associate($user);
            $document->save();

            $this->syncItems($document, $items);

            return $document->fresh('items');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(WarehouseDocument $document, array $attributes): WarehouseDocument
    {
        $payload = $this->validate($attributes, $document);

        $items = $payload['items'] ?? null;
        unset($payload['items']);

        $payloadType = $payload['type'] ?? $document->type;
        $typeChanged = $payloadType !== $document->type;

        if ($typeChanged && empty($payload['number'])) {
            $payload['number'] = $this->generateDocumentNumber($document->user, $payloadType);
        }

        if (array_key_exists('number', $payload)) {
            if (empty($payload['number'])) {
                unset($payload['number']);
            } elseif ($payload['number'] !== $document->number || $typeChanged) {
                $payload['number'] = trim($payload['number']);
                $this->assertNumberUnique($document->user, $payload['number'], $document);
            }
        }

        return $this->db->transaction(function () use ($document, $payload, $items) {
            $document->fill($payload)->save();

            if ($items !== null) {
                $this->syncItems($document, $items);
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
        $normalized = $attributes;
        if (array_key_exists('type', $normalized) && $normalized['type'] !== null) {
            $normalized['type'] = strtoupper(trim((string) $normalized['type']));
        }
        if (array_key_exists('number', $normalized) && is_string($normalized['number'])) {
            $normalized['number'] = trim($normalized['number']);
        }

        $rules = [
            'warehouse_location_id' => ['nullable', 'exists:warehouse_locations,id'],
            'contractor_id' => ['nullable', 'exists:contractors,id'],
            'type' => ['required', 'string', Rule::in($this->supportedTypes)],
            'number' => ['nullable', 'string', 'max:50'],
            'issued_at' => ['required', 'date'],
            'metadata' => ['nullable', 'array'],
            'status' => ['nullable', 'in:draft,posted'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric'],
            'items.*.unit_price' => ['nullable', 'numeric'],
            'items.*.vat_rate' => ['nullable', 'integer'],
        ];

        $validated = $this->validator->make($normalized, $rules)->validate();
        $validated['type'] = strtoupper($validated['type']);

        if (array_key_exists('number', $validated)) {
            $validated['number'] = $validated['number'] !== null ? trim($validated['number']) : null;
            if ($validated['number'] === '') {
                unset($validated['number']);
            }
        }

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

    protected function generateDocumentNumber(User $user, string $type): string
    {
        $type = strtoupper(trim($type));
        if (! in_array($type, $this->supportedTypes, true)) {
            $type = 'PZ';
        }

        $setting = WarehouseDocumentSetting::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->lockForUpdate()
            ->first();

        if (! $setting) {
            $setting = $user->warehouseDocumentSettings()->create([
                'type' => $type,
                'prefix' => $type.'/',
                'suffix' => null,
                'next_number' => 1,
                'padding' => 4,
                'reset_period' => 'none',
                'last_reset_at' => now(),
            ]);
        }

        if ($this->shouldReset($setting)) {
            $setting->next_number = 1;
            $setting->last_reset_at = now();
        }

        $sequence = $setting->next_number;
        $number = $this->formatDocumentNumber($setting, $sequence);

        while ($this->documentNumberExists($user, $number)) {
            $sequence++;
            $number = $this->formatDocumentNumber($setting, $sequence);
        }

        $setting->next_number = $sequence + 1;
        $setting->last_reset_at ??= now();
        $setting->save();

        return $number;
    }

    protected function formatDocumentNumber(WarehouseDocumentSetting $setting, int $sequence): string
    {
        $width = max(1, (int) $setting->padding);

        return ($setting->prefix ?? '')
            . str_pad((string) $sequence, $width, '0', STR_PAD_LEFT)
            . ($setting->suffix ?? '');
    }

    protected function shouldReset(WarehouseDocumentSetting $setting): bool
    {
        if ($setting->reset_period === 'none') {
            return false;
        }

        $last = $setting->last_reset_at ?? $setting->created_at;
        $now = Carbon::now();

        return match ($setting->reset_period) {
            'daily' => !$last || $last->isSameDay($now) === false,
            'monthly' => !$last || ($last->year !== $now->year || $last->month !== $now->month),
            'yearly' => !$last || $last->year !== $now->year,
            default => false,
        };
    }

    protected function documentNumberExists(User $user, string $number, ?WarehouseDocument $ignore = null): bool
    {
        return $user->warehouseDocuments()
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->id))
            ->where('number', $number)
            ->exists();
    }

    protected function assertNumberUnique(User $user, string $number, ?WarehouseDocument $ignore = null): void
    {
        if ($this->documentNumberExists($user, $number, $ignore)) {
            throw new \InvalidArgumentException('Dokument o takim numerze już istnieje.');
        }
    }
}
