@extends('layouts.dashboard')

@section('title', 'Nowy produkt')
@section('header', 'Dodaj produkt')
@section('subheading', 'Uzupełnij podstawowe informacje o produkcie')

@section('content')
    <div class="max-w-4xl space-y-6">
        <x-ui.card>
            <form method="POST" action="{{ route('products.store') }}" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <x-ui.input label="Nazwa" name="name" :value="old('name')" required />
                    <x-ui.input label="SKU" name="sku" :value="old('sku')" />

                    <x-ui.select
                        label="Katalog"
                        name="catalog_id"
                        :value="old('catalog_id')"
                        :options="$catalogs->pluck('name', 'id')->toArray()"
                        placeholder="Wybierz katalog"
                        required
                    />

                    <x-ui.select
                        label="Kategoria"
                        name="category_id"
                        :value="old('category_id')"
                        :options="$categories->mapWithKeys(fn($category) => [$category->id => ($category->catalog?->name ? $category->catalog->name.' › ' : '').$category->name])->toArray()"
                        placeholder="Wybierz kategorię"
                    />

                    <x-ui.select
                        label="Producent"
                        name="manufacturer_id"
                        :value="old('manufacturer_id')"
                        :options="$manufacturers->pluck('name', 'id')->toArray()"
                        placeholder="Wybierz producenta"
                    />

                    <x-ui.select
                        label="Status"
                        name="status"
                        :value="old('status', 'draft')"
                        :options="['draft' => 'Szkic', 'active' => 'Aktywny', 'archived' => 'Archiwum']"
                    />
                </div>

                <x-ui.input label="Slug" name="slug" :value="old('slug')" help-text="Pozostaw puste aby wygenerować automatycznie." />

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <x-ui.input label="Cena zakupu netto" name="purchase_price_net" type="number" step="0.01" :value="old('purchase_price_net')" />
                    <x-ui.input label="VAT zakupu (%)" name="purchase_vat_rate" type="number" step="1" :value="old('purchase_vat_rate')" />
                    <x-ui.input label="Cena sprzedaży netto" name="sale_price_net" type="number" step="0.01" :value="old('sale_price_net')" />
                    <x-ui.input label="VAT sprzedaży (%)" name="sale_vat_rate" type="number" step="1" :value="old('sale_vat_rate')" />
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Kwoty brutto zostaną obliczone automatycznie na podstawie stawek VAT.</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="description">Opis</label>
                    <textarea id="description" name="description" class="mt-2 block w-full rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100" rows="5">{{ old('description') }}</textarea>
                </div>

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">Zapisz produkt</x-ui.button>
                    <x-ui.button as="a" :href="route('products.index')" variant="ghost">Anuluj</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
