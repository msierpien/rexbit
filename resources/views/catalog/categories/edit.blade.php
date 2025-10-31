@extends('layouts.dashboard')

@section('title', 'Edycja kategorii')
@section('header', 'Edycja kategorii')
@section('subheading', 'Aktualizuj strukturę kategorii produktów')

@section('content')
    <div class="max-w-3xl">
        <x-ui.card>
            <form method="POST" action="{{ route('product-categories.update', $category) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <x-ui.input label="Nazwa" name="name" :value="old('name', $category->name)" required />
                <x-ui.input label="Slug" name="slug" :value="old('slug', $category->slug)" />

                <x-ui.select
                    label="Katalog"
                    name="catalog_id"
                    :value="old('catalog_id', $category->catalog_id)"
                    :options="$catalogs->pluck('name', 'id')->toArray()"
                    placeholder="Wybierz katalog"
                    required
                />

                <x-ui.select
                    label="Kategoria nadrzędna"
                    name="parent_id"
                    :value="old('parent_id', $category->parent_id)"
                    :options="$categories->mapWithKeys(fn($categoryOption) => [$categoryOption->id => ($categoryOption->catalog?->name ? $categoryOption->catalog->name.' › ' : '').$categoryOption->name])->toArray()"
                    placeholder="Brak"
                />

                <x-ui.input label="Pozycja" name="position" type="number" :value="old('position', $category->position)" />

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">Zapisz zmiany</x-ui.button>
                    <x-ui.button as="a" :href="route('product-categories.index')" variant="ghost">Wróć</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
