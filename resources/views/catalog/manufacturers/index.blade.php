@extends('layouts.dashboard')

@section('title', 'Producenci')
@section('header', 'Producenci')
@section('subheading', 'Zarządzaj listą producentów przypisanych do produktów')

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Producenci</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Dodaj producentów, aby mapować produkty na konkretnych wytwórców.</p>
            </div>

            <x-ui.button as="a" :href="route('manufacturers.create')">Dodaj producenta</x-ui.button>
        </div>

        @if (session('status'))
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        @endif

        <x-ui.card>
            <x-ui.table>
                <x-slot name="head">
                    <x-ui.table.head>
                        <x-ui.table.heading>Nazwa</x-ui.table.heading>
                        <x-ui.table.heading>Slug</x-ui.table.heading>
                        <x-ui.table.heading>Strona WWW</x-ui.table.heading>
                        <x-ui.table.heading>Produkty</x-ui.table.heading>
                        <x-ui.table.heading align="right">Akcje</x-ui.table.heading>
                    </x-ui.table.head>
                </x-slot>

                @forelse ($manufacturers as $manufacturer)
                    <x-ui.table.row>
                        <x-ui.table.cell class="font-medium text-gray-900 dark:text-gray-100">{{ $manufacturer->name }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $manufacturer->slug }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $manufacturer->website ?? '—' }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $manufacturer->products_count }}</x-ui.table.cell>
                        <x-ui.table.cell align="right">
                            <div class="flex items-center justify-end gap-2">
                                <x-ui.button as="a" :href="route('manufacturers.edit', $manufacturer)" variant="outline" size="sm">Edytuj</x-ui.button>
                                <form method="POST" action="{{ route('manufacturers.destroy', $manufacturer) }}" onsubmit="return confirm('Usunąć producenta?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="outline-danger" size="sm">Usuń</x-ui.button>
                                </form>
                            </div>
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @empty
                    <x-ui.table.row>
                        <x-ui.table.cell colspan="5" class="text-center text-sm text-gray-500 dark:text-gray-400">
                            Brak producentów.
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforelse
            </x-ui.table>
        </x-ui.card>

        {{ $manufacturers->links() }}
    </div>
@endsection
