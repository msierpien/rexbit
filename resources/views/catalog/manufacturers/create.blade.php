@extends('layouts.dashboard')

@section('title', 'Nowy producent')
@section('header', 'Dodaj producenta')
@section('subheading', 'Przechowuj informacje o producentach produktów')

@section('content')
    <div class="max-w-3xl">
        <x-ui.card>
            <form method="POST" action="{{ route('manufacturers.store') }}" class="space-y-6">
                @csrf

                <x-ui.input label="Nazwa" name="name" :value="old('name')" required />
                <x-ui.input label="Slug" name="slug" :value="old('slug')" help-text="Pozostaw puste aby wygenerować automatycznie." />
                <x-ui.input label="Strona WWW" name="website" :value="old('website')" />

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="contacts">Dodatkowe kontakty (JSON)</label>
                    <textarea id="contacts" name="contacts" rows="4" class="mt-2 block w-full rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">{{ old('contacts') }}</textarea>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Przykład: {"email":"support@example.com","phone":"+48 123 456 789"}</p>
                </div>

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">Zapisz producenta</x-ui.button>
                    <x-ui.button as="a" :href="route('manufacturers.index')" variant="ghost">Anuluj</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
