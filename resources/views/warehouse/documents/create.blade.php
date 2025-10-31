@extends('layouts.dashboard')

@section('title', 'Nowy dokument magazynowy')
@section('header', 'Nowy dokument magazynowy')
@section('subheading', 'Wprowadź dostawę lub wydanie towaru')

@section('content')
    <div class="space-y-6">
        <x-ui.card>
            <form method="POST" action="{{ route('warehouse.documents.store') }}" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <x-ui.input
                        label="Numer"
                        name="number"
                        :value="old('number')"
                        placeholder="Generowany automatycznie"
                        help-text="Pozostaw puste, aby numer został nadany automatycznie."
                    />
                    <x-ui.select
                        label="Typ dokumentu"
                        name="type"
                        :value="old('type', 'PZ')"
                        :options="['PZ' => 'PZ - Przyjęcie zewnętrzne', 'WZ' => 'WZ - Wydanie zewnętrzne', 'IN' => 'IN - Przyjęcie wewnętrzne', 'OUT' => 'OUT - Wydanie wewnętrzne']"
                        required
                    />
                    <x-ui.select
                        label="Magazyn"
                        name="warehouse_location_id"
                        :value="old('warehouse_location_id')"
                        :options="$warehouses->pluck('name', 'id')->toArray()"
                        placeholder="Wybierz magazyn"
                    />
                    <x-ui.input label="Data" name="issued_at" type="date" :value="old('issued_at', today()->format('Y-m-d'))" required />
                </div>

                <x-ui.select
                    label="Kontrahent"
                    name="contractor_id"
                    :value="old('contractor_id')"
                    :options="$contractors->pluck('name', 'id')->toArray()"
                    placeholder="Wybierz kontrahenta"
                />

                <p class="text-xs text-gray-500 dark:text-gray-400">Numer dokumentu zostanie nadany automatycznie na podstawie konfiguracji, jeśli pozostawisz go pustym.</p>

                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Pozycje dokumentu</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Po zapisaniu będziesz mógł dodać pozycje w widoku edycji dokumentu.</p>
                </div>

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">Zapisz dokument</x-ui.button>
                    <x-ui.button as="a" :href="route('warehouse.documents.index')" variant="ghost">Anuluj</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
