@extends('layouts.dashboard')

@section('title', 'Ustawienia magazynu')
@section('header', 'Ustawienia magazynu')
@section('subheading', 'Konfiguruj magazyny oraz zasady numeracji dokumentów')

@section('content')
    <x-ui.card title="Magazyny">
        <div class="space-y-3">
            @forelse ($warehouses as $warehouse)
                <div class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
                    <div>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $warehouse->name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Kod: {{ $warehouse->code ?? 'brak' }} | Domyślny: {{ $warehouse->is_default ? 'tak' : 'nie' }}</p>
                    </div>
                    <x-ui.button variant="outline" size="sm">Edytuj</x-ui.button>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Brak zdefiniowanych magazynów. Dodaj pierwszy magazyn, aby rozpocząć pracę.</p>
            @endforelse
        </div>
    </x-ui.card>

    <x-ui.card title="Numeracja dokumentów" class="mt-6">
        <p class="text-sm text-gray-600 dark:text-gray-300">Sekcja konfiguracji numeracji dokumentów zostanie opracowana w kolejnym kroku.</p>
    </x-ui.card>
@endsection
