@extends('layouts.dashboard')

@section('title', 'Edycja katalogu')
@section('header', 'Edycja katalogu produktów')
@section('subheading', 'Aktualizuj informacje o katalogu i przypisanych danych')

@section('content')
    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        @endif

        <x-ui.card>
            <form method="POST" action="{{ route('product-catalogs.update', $catalog) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <x-ui.input label="Nazwa" name="name" :value="old('name', $catalog->name)" required />
                <x-ui.input label="Slug" name="slug" :value="old('slug', $catalog->slug)" />

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="description">Opis</label>
                    <textarea id="description" name="description" rows="4" class="mt-2 block w-full rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">{{ old('description', $catalog->description) }}</textarea>
                </div>

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">Zapisz zmiany</x-ui.button>
                    <x-ui.button as="a" :href="route('product-catalogs.index')" variant="ghost">Wróć</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
