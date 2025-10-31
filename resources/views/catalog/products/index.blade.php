@extends('layouts.dashboard')

@section('title', 'Produkty')
@section('header', 'Lista produktów')
@section('subheading', 'Zarządzaj katalogiem swoich produktów')

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Produkty</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Dodawaj nowe produkty i edytuj istniejące wpisy.</p>
            </div>

            <x-ui.button as="a" :href="route('products.create')">
                Dodaj produkt
            </x-ui.button>
        </div>

        @if (session('status'))
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        @endif

        <x-ui.card>
            <x-ui.table>
                <x-slot name="head">
                    <x-ui.table.head>
                        <x-ui.table.heading>Nazwa</x-ui.table.heading>
                    <x-ui.table.heading>Katalog</x-ui.table.heading>
                    <x-ui.table.heading>Kategoria</x-ui.table.heading>
                    <x-ui.table.heading>Producent</x-ui.table.heading>
                    <x-ui.table.heading>SKU</x-ui.table.heading>
                    <x-ui.table.heading>Status</x-ui.table.heading>
                        <x-ui.table.heading align="right">Akcje</x-ui.table.heading>
                    </x-ui.table.head>
                </x-slot>

                @forelse ($products as $product)
                    <x-ui.table.row>
                        <x-ui.table.cell class="font-medium text-gray-900 dark:text-gray-100">{{ $product->name }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $product->catalog?->name ?? '—' }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $product->category?->name ?? '—' }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $product->manufacturer?->name ?? '—' }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $product->sku ?? '—' }}</x-ui.table.cell>
                        <x-ui.table.cell>
                            <x-ui.badge :variant="$product->status->value === 'active' ? 'success' : ($product->status->value === 'draft' ? 'neutral' : 'warning')">
                                {{ $product->status->value }}
                            </x-ui.badge>
                        </x-ui.table.cell>
                        <x-ui.table.cell align="right">
                            <div class="flex items-center justify-end gap-2">
                                <x-ui.button as="a" :href="route('products.edit', $product)" variant="outline" size="sm">Edytuj</x-ui.button>
                                <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Czy na pewno chcesz usunąć ten produkt?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="outline-danger" size="sm">Usuń</x-ui.button>
                                </form>
                            </div>
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @empty
                    <x-ui.table.row>
                        <x-ui.table.cell colspan="7" class="text-center text-sm text-gray-500 dark:text-gray-400">
                            Brak produktów. Dodaj pierwszy produkt aby rozpocząć sprzedaż.
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforelse
            </x-ui.table>
        </x-ui.card>

        {{ $products->links() }}
    </div>
@endsection
