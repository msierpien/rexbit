@extends('layouts.dashboard')

@section('title', 'Panel użytkownika')
@section('header', 'Witaj ponownie!')
@section('subheading', 'Dostęp do Twoich najważniejszych danych')

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-ui.stat-tile
            label="Ostatnia aktywność"
            value="2 godziny temu"
            trend="Dziękujemy, że korzystasz z platformy"
            trendVariant="neutral"
        />
        <x-ui.stat-tile
            label="Powiadomienia"
            value="5"
            trend="2 nowe od ostatniego logowania"
            trendVariant="warning"
        />
        <x-ui.card>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Status konta</h3>
            <div class="mt-4 inline-flex items-center rounded-full bg-green-100 px-4 py-2 text-sm font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">
                <span class="mr-2 h-2 w-2 rounded-full bg-green-500"></span> Aktywne
            </div>
        </x-ui.card>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card title="Nadchodzące zadania">
            <ul class="space-y-4">
                <li class="flex justify-between text-sm text-gray-600 dark:text-gray-300">
                    <div>
                        <p class="font-medium text-gray-900 dark:text-gray-100">Uzupełnij profil</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Dodaj zdjęcie i podstawowe informacje</p>
                    </div>
                    <x-ui.badge variant="primary" size="sm">15 min</x-ui.badge>
                </li>
                <li class="flex justify-between text-sm text-gray-600 dark:text-gray-300">
                    <div>
                        <p class="font-medium text-gray-900 dark:text-gray-100">Przeczytaj raport</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Podsumowanie aktywności z ostatniego tygodnia</p>
                    </div>
                    <x-ui.badge variant="warning" size="sm">45 min</x-ui.badge>
                </li>
            </ul>
        </x-ui.card>

        <x-ui.card title="Szybkie akcje">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-ui.button as="a" href="#" variant="outline">Przegląd profilu</x-ui.button>
                <x-ui.button as="a" href="#" variant="outline">Ustawienia konta</x-ui.button>
                <x-ui.button as="a" href="#" variant="outline">Pomoc i wsparcie</x-ui.button>
                <x-ui.button as="a" href="#" variant="outline">Zgłoś problem</x-ui.button>
            </div>
        </x-ui.card>
    </div>
@endsection
