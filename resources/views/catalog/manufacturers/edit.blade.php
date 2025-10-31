@extends('layouts.dashboard')

@section('title', 'Edycja producenta')
@section('header', 'Edycja producenta')
@section('subheading', 'Aktualizuj dane producenta')

@section('content')
    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        @endif

        <x-ui.card>
            <form method="POST" action="{{ route('manufacturers.update', $manufacturer) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <x-ui.input label="Nazwa" name="name" :value="old('name', $manufacturer->name)" required />
                <x-ui.input label="Slug" name="slug" :value="old('slug', $manufacturer->slug)" />
                <x-ui.input label="Strona WWW" name="website" :value="old('website', $manufacturer->website)" />

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="contacts">Dodatkowe kontakty (JSON)</label>
                    <textarea id="contacts" name="contacts" rows="4" class="mt-2 block w-full rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">{{ old('contacts', $manufacturer->contacts ? json_encode($manufacturer->contacts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
                </div>

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">Zapisz zmiany</x-ui.button>
                    <x-ui.button as="a" :href="route('manufacturers.index')" variant="ghost">Wróć</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
