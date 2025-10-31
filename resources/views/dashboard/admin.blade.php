@extends('layouts.dashboard')

@section('title', 'Panel administratora')
@section('header', 'Panel administratora')
@section('subheading', 'Zarządzaj użytkownikami i monitoruj system')

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-ui.stat-tile
            label="Aktywni użytkownicy"
            value="128"
            trend="+12% w ostatnim tygodniu"
            trendVariant="success"
        />

        <x-ui.stat-tile
            label="Nowe zgłoszenia"
            value="24"
            trend="6 oczekuje na reakcję"
            trendVariant="warning"
        />

        <x-ui.card>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Zużycie zasobów</h3>
            <div class="mt-4">
                <div class="flex justify-between text-xs font-medium text-gray-600 dark:text-gray-300">
                    <span>Serwery</span>
                    <span>68%</span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="h-2 w-2/3 rounded-full bg-blue-500"></div>
                </div>
            </div>
        </x-ui.card>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card title="Ostatnie logowania" subtitle="Monitoruj aktywność administratorów i użytkowników">
            <ul class="space-y-4">
                <li class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-medium text-gray-900 dark:text-gray-100">Anna Kowalska</span>
                    <span>5 minut temu</span>
                </li>
                <li class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-medium text-gray-900 dark:text-gray-100">Jan Nowak</span>
                    <span>12 minut temu</span>
                </li>
                <li class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-medium text-gray-900 dark:text-gray-100">Magda Wiśniewska</span>
                    <span>31 minut temu</span>
                </li>
            </ul>
        </x-ui.card>

        <x-ui.card title="Statystyki ról" subtitle="Rozkład uprawnień w systemie">
            <div class="space-y-4">
                <div>
                    <div class="flex items-center justify-between text-sm font-medium text-gray-600 dark:text-gray-300">
                        <span>Administratorzy</span>
                        <span>8</span>
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-2 w-1/5 rounded-full bg-purple-500"></div>
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between text-sm font-medium text-gray-600 dark:text-gray-300">
                        <span>Użytkownicy</span>
                        <span>120</span>
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-2 w-4/5 rounded-full bg-teal-500"></div>
                    </div>
                </div>
            </div>
        </x-ui.card>
    </div>
@endsection
