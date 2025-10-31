@extends('layouts.dashboard')

@section('title', 'Kategorie produktów')
@section('header', 'Kategorie produktów')
@section('subheading', 'Zarządzaj strukturą drzewa kategorii')

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Kategorie</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Twórz i układaj kategorie, aby porządkować katalog produktów.</p>
            </div>

            <x-ui.button as="a" :href="route('product-categories.create')">Dodaj kategorię</x-ui.button>
        </div>

        @if (session('status'))
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        @endif

        <x-ui.card>
            <div class="space-y-6">
                @forelse ($catalogs as $catalog)
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">{{ $catalog->name }}</h3>
                        <div class="space-y-3">
                            @forelse ($catalog->categories as $category)
                                @include('catalog.categories.partials.node', ['category' => $category])
                            @empty
                                <p class="text-sm text-gray-500 dark:text-gray-400">Brak kategorii w tym katalogu.</p>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Brak kategorii. Dodaj pierwszą kategorię aby rozpocząć.</p>
                @endforelse
            </div>
        </x-ui.card>
    </div>
@endsection
