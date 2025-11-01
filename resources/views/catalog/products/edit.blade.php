@extends('layouts.dashboard')

@section('title', 'Edycja produktu')
@section('header', 'Edycja produktu')
@section('subheading', 'Aktualizuj informacje o produkcie')

@section('content')
    <div class="max-w-4xl space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        @endif

        <x-ui.card title="Podstawowe informacje">
            <form method="POST" action="{{ route('products.update', $product) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <x-ui.input label="Nazwa" name="name" :value="old('name', $product->name)" required />
                    <x-ui.input label="SKU" name="sku" :value="old('sku', $product->sku)" />

                    <x-ui.input label="EAN" name="ean" :value="old('ean', $product->ean)" />

                    <x-ui.select
                        label="Katalog"
                        name="catalog_id"
                        :value="old('catalog_id', $product->catalog_id)"
                        :options="$catalogs->pluck('name', 'id')->toArray()"
                        required
                    />

                    <x-ui.select
                        label="Kategoria"
                        name="category_id"
                        :value="old('category_id', $product->category_id)"
                        :options="$categories->mapWithKeys(fn($categoryOption) => [$categoryOption->id => ($categoryOption->catalog?->name ? $categoryOption->catalog->name.' › ' : '').$categoryOption->name])->toArray()"
                        placeholder="Wybierz kategorię"
                    />

                    <x-ui.select
                        label="Producent"
                        name="manufacturer_id"
                        :value="old('manufacturer_id', $product->manufacturer_id)"
                        :options="$manufacturers->pluck('name', 'id')->toArray()"
                        placeholder="Wybierz producenta"
                    />

                    <x-ui.select
                        label="Status"
                        name="status"
                        :value="old('status', $product->status->value)"
                        :options="['draft' => 'Szkic', 'active' => 'Aktywny', 'archived' => 'Archiwum']"
                    />
                </div>

                <x-ui.input label="Slug" name="slug" :value="old('slug', $product->slug)" />

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <x-ui.input label="Cena zakupu netto" name="purchase_price_net" type="number" step="0.01" :value="old('purchase_price_net', $product->purchase_price_net)" />
                    <x-ui.input label="VAT zakupu (%)" name="purchase_vat_rate" type="number" step="1" :value="old('purchase_vat_rate', $product->purchase_vat_rate)" />
                    <x-ui.input label="Cena sprzedaży netto" name="sale_price_net" type="number" step="0.01" :value="old('sale_price_net', $product->sale_price_net)" />
                    <x-ui.input label="VAT sprzedaży (%)" name="sale_vat_rate" type="number" step="1" :value="old('sale_vat_rate', $product->sale_vat_rate)" />
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Cena sprzedaży brutto: {{ $product->sale_price_gross ? number_format($product->sale_price_gross, 2) : '—' }} | Cena zakupu brutto: {{ $product->purchase_price_gross ? number_format($product->purchase_price_gross, 2) : '—' }}</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="description">Opis</label>
                    <textarea id="description" name="description" class="mt-2 block w-full rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100" rows="6">{{ old('description', $product->description) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="images">Zdjęcia (URL)</label>
                    <textarea id="images" name="images" class="mt-2 block w-full rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100" rows="3" placeholder="Wpisz URL zdjęć oddzielone przecinkami">{{ old('images', is_array($product->images) ? implode(', ', $product->images) : $product->images) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Wpisz adresy URL zdjęć oddzielone przecinkami</p>
                </div>

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">Zapisz zmiany</x-ui.button>
                    <x-ui.button as="a" :href="route('products.index')" variant="ghost">Wróć</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
