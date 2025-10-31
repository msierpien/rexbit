@props([
    'products',
    'items' => [],
])

@php
    $componentId = 'document-items-' . \Illuminate\Support\Str::uuid()->toString();

    $preparedItems = collect(old('items', $items))
        ->map(function ($item) {
            if ($item instanceof \App\Models\WarehouseDocumentItem) {
                return [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'vat_rate' => $item->vat_rate,
                ];
            }

            if (is_array($item)) {
                return [
                    'product_id' => $item['product_id'] ?? null,
                    'quantity' => $item['quantity'] ?? null,
                    'unit_price' => $item['unit_price'] ?? null,
                    'vat_rate' => $item['vat_rate'] ?? null,
                ];
            }

            return [
                'product_id' => null,
                'quantity' => null,
                'unit_price' => null,
                'vat_rate' => null,
            ];
        })
        ->filter(fn ($item) => $item['product_id'] !== null || $item['quantity'] !== null || $item['unit_price'] !== null || $item['vat_rate'] !== null)
        ->values()
        ->toArray();

    if (empty($preparedItems)) {
        $preparedItems = [[
            'product_id' => null,
            'quantity' => null,
            'unit_price' => null,
            'vat_rate' => null,
        ]];
    }
@endphp

<div
    id="{{ $componentId }}"
    data-document-items
    data-existing-items='@json($preparedItems)'
    data-next-index="{{ count($preparedItems) }}"
    class="space-y-4"
>
    @if ($errors->has('items') || $errors->has('items.*.product_id') || $errors->has('items.*.quantity'))
        <x-ui.alert variant="danger">
            @if ($errors->has('items'))
                {{ $errors->first('items') }}
            @else
                Proszę uzupełnić wszystkie wymagane pola pozycji dokumentu.
            @endif
        </x-ui.alert>
    @endif

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/40">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Produkt</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Ilość</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Cena netto</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">VAT %</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody data-items-body class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800"></tbody>
        </table>
    </div>

    <x-ui.button type="button" variant="outline" size="sm" data-add-item>
        Dodaj pozycję
    </x-ui.button>

    <template data-row-template>
        <tr data-item-row>
            <td class="px-4 py-3">
                <select
                    data-field="product_id"
                    data-name="items[__INDEX__][product_id]"
                    class="block w-full rounded-lg border border-gray-300 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    required
                >
                    <option value="">Wybierz produkt</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </td>
            <td class="px-4 py-3">
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    data-field="quantity"
                    data-name="items[__INDEX__][quantity]"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    required
                >
            </td>
            <td class="px-4 py-3">
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    data-field="unit_price"
                    data-name="items[__INDEX__][unit_price]"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                >
            </td>
            <td class="px-4 py-3">
                <input
                    type="number"
                    step="1"
                    min="0"
                    data-field="vat_rate"
                    data-name="items[__INDEX__][vat_rate]"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                >
            </td>
            <td class="px-4 py-3 text-right">
                <button
                    type="button"
                    class="text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                    data-remove-item
                >
                    Usuń
                </button>
            </td>
        </tr>
    </template>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-document-items]').forEach(function (wrapper) {
                    const body = wrapper.querySelector('[data-items-body]');
                    const template = wrapper.querySelector('template[data-row-template]');
                    let nextIndex = Number(wrapper.getAttribute('data-next-index')) || 0;
                    let existingItems = [];

                    try {
                        existingItems = JSON.parse(wrapper.getAttribute('data-existing-items') || '[]');
                    } catch (error) {
                        existingItems = [];
                    }

                    function hydrateRow(row, index, data) {
                        row.querySelectorAll('[data-name]').forEach(function (element) {
                            const templateName = element.getAttribute('data-name');
                            element.setAttribute('name', templateName.replace(/__INDEX__/g, index));
                        });

                        if (data && typeof data === 'object') {
                            Object.keys(data).forEach(function (key) {
                                const field = row.querySelector('[data-field=\"' + key + '\"]');
                                if (!field) {
                                    return;
                                }

                                const value = data[key];
                                if (field.tagName === 'SELECT') {
                                    field.value = value !== null && value !== undefined ? String(value) : '';
                                } else {
                                    field.value = value !== null && value !== undefined ? value : '';
                                }
                            });
                        }
                    }

                    function addRow(data) {
                        const fragment = template.content.cloneNode(true);
                        const row = fragment.querySelector('[data-item-row]');
                        hydrateRow(row, nextIndex, data);
                        body.appendChild(fragment);
                        nextIndex++;
                    }

                    const addButton = wrapper.querySelector('[data-add-item]');
                    if (addButton) {
                        addButton.addEventListener('click', function () {
                            addRow({});
                        });
                    }

                    body.addEventListener('click', function (event) {
                        const trigger = event.target.closest('[data-remove-item]');
                        if (trigger) {
                            event.preventDefault();
                            const row = trigger.closest('[data-item-row]');
                            if (row) {
                                row.remove();
                            }
                        }
                    });

                    if (existingItems.length) {
                        existingItems.forEach(function (item) {
                            addRow(item);
                        });
                    } else {
                        addRow({});
                    }
                });
            });
        </script>
    @endpush
@endonce
