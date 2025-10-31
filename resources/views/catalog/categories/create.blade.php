@extends('layouts.dashboard')

@section('title', 'Nowa kategoria')
@section('header', 'Dodaj kategorię')
@section('subheading', 'Zgrupuj produkty w strukturze kategorii')

@section('content')
    <div class="max-w-3xl">
        <x-ui.card>
            <form method="POST" action="{{ route('product-categories.store') }}" class="space-y-6">
                @csrf

                <x-ui.input label="Nazwa" name="name" :value="old('name')" required />
                <x-ui.input label="Slug" name="slug" :value="old('slug')" help-text="Pozostaw puste aby wygenerować automatycznie." />

                <x-ui.select
                    label="Katalog"
                    name="catalog_id"
                    :value="old('catalog_id')"
                    :options="$catalogs->pluck('name', 'id')->toArray()"
                    placeholder="Wybierz katalog"
                    required
                />

                <x-ui.select
                    label="Kategoria nadrzędna"
                    name="parent_id"
                    :value="old('parent_id')"
                    :options="$categories->mapWithKeys(fn($category) => [$category->id => ($category->catalog?->name ? $category->catalog->name.' › ' : '').$category->name])->toArray()"
                    placeholder="Brak"
                />

                <x-ui.input label="Pozycja" name="position" type="number" :value="old('position', 0)" />

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">Zapisz kategorię</x-ui.button>
                    <x-ui.button as="a" :href="route('product-categories.index')" variant="ghost">Anuluj</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
