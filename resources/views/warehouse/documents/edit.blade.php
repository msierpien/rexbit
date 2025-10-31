@extends('layouts.dashboard')

@section('title', 'Edycja dokumentu magazynowego')
@section('header', 'Edycja dokumentu magazynowego')
@section('subheading', 'Aktualizuj pozycje oraz dane podstawowe dokumentu')

@section('content')
    <div class="space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        @endif

        <x-ui.card title="Podstawowe informacje">
            <form method="POST" action="{{ route('warehouse.documents.update', $document) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <x-ui.input label="Numer" name="number" :value="old('number', $document->number)" />

                    <x-ui.select
                        label="Typ dokumentu"
                        name="type"
                        :value="old('type', $document->type)"
                        :options="['PZ' => 'PZ', 'WZ' => 'WZ', 'IN' => 'IN', 'OUT' => 'OUT']"
                        required
                    />

                    <x-ui.select
                        label="Magazyn"
                        name="warehouse_location_id"
                        :value="old('warehouse_location_id', $document->warehouse_location_id)"
                        :options="$warehouses->pluck('name', 'id')->toArray()"
                        placeholder="Wybierz magazyn"
                    />

                    <x-ui.input label="Data" name="issued_at" type="date" :value="old('issued_at', $document->issued_at->format('Y-m-d'))" required />
                </div>

                <x-ui.select
                    label="Kontrahent"
                    name="contractor_id"
                    :value="old('contractor_id', $document->contractor_id)"
                    :options="$contractors->pluck('name', 'id')->toArray()"
                    placeholder="Wybierz kontrahenta"
                />
                <p class="text-xs text-gray-500 dark:text-gray-400">Pozostaw numer pusty, aby wygenerować go automatycznie zgodnie z konfiguracją.</p>

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">Zapisz zmiany</x-ui.button>
                    <x-ui.button as="a" :href="route('warehouse.documents.index')" variant="ghost">Wróć</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="Pozycje dokumentu" subtitle="Dodawaj produkty oraz ilości wchodzące w skład dokumentu">
            <p class="text-sm text-gray-500 dark:text-gray-400">Moduł edycji pozycji dokumentu będzie rozbudowany w kolejnym etapie. Na ten moment możesz dodać pozycje poprzez API lub bezpośrednio w bazie danych.</p>
        </x-ui.card>
    </div>
@endsection
