@extends('layouts.dashboard')

@section('title', 'Katalogi produktów')
@section('header', 'Katalogi produktów')
@section('subheading', 'Twórz niezależne katalogi dla różnych kanałów sprzedaży')

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Katalogi</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Każdy katalog posiada własny zestaw produktów i kategorii.</p>
            </div>

            <x-ui.button as="a" :href="route('product-catalogs.create')">Dodaj katalog</x-ui.button>
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
                        <x-ui.table.heading>Produkty</x-ui.table.heading>
                        <x-ui.table.heading align="right">Akcje</x-ui.table.heading>
                    </x-ui.table.head>
                </x-slot>

                @forelse ($catalogs as $catalog)
                    <x-ui.table.row>
                        <x-ui.table.cell class="font-medium text-gray-900 dark:text-gray-100">
                            <div>{{ $catalog->name }}</div>
                            @if ($catalog->description)
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($catalog->description, 80) }}</p>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell>{{ $catalog->slug }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $catalog->products_count }}</x-ui.table.cell>
                        <x-ui.table.cell align="right">
                            <div class="flex items-center justify-end gap-2">
                                <x-ui.button as="a" :href="route('product-catalogs.edit', $catalog)" variant="outline" size="sm">Edytuj</x-ui.button>
                                <form method="POST" action="{{ route('product-catalogs.destroy', $catalog) }}" onsubmit="return confirm('Usunąć katalog?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="outline-danger" size="sm">Usuń</x-ui.button>
                                </form>
                            </div>
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @empty
                    <x-ui.table.row>
                        <x-ui.table.cell colspan="4" class="text-center text-sm text-gray-500 dark:text-gray-400">
                            Brak katalogów.
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforelse
            </x-ui.table>
        </x-ui.card>

        {{ $catalogs->links() }}
    </div>
@endsection
