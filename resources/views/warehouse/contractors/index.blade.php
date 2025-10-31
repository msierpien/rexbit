@extends('layouts.dashboard')

@section('title', 'Kontrahenci')
@section('header', 'Kontrahenci')
@section('subheading', 'Zarządzaj dostawcami i odbiorcami dla dokumentów magazynowych')

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Kontrahenci</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Dodaj kontrahentów, aby łatwiej wystawiać dokumenty PZ i WZ.</p>
            </div>

            <x-ui.button as="a" :href="route('warehouse.contractors.create')">Dodaj kontrahenta</x-ui.button>
        </div>

        @if (session('status'))
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        @endif

        <x-ui.card>
            <x-ui.table>
                <x-slot name="head">
                    <x-ui.table.head>
                        <x-ui.table.heading>Nazwa</x-ui.table.heading>
                        <x-ui.table.heading>NIP</x-ui.table.heading>
                        <x-ui.table.heading>Email</x-ui.table.heading>
                        <x-ui.table.heading>Typ</x-ui.table.heading>
                        <x-ui.table.heading align="right">Akcje</x-ui.table.heading>
                    </x-ui.table.head>
                </x-slot>

                @forelse ($contractors as $contractor)
                    <x-ui.table.row>
                        <x-ui.table.cell class="font-medium text-gray-900 dark:text-gray-100">{{ $contractor->name }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $contractor->tax_id ?? '—' }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $contractor->email ?? '—' }}</x-ui.table.cell>
                        <x-ui.table.cell>
                            @php
                                $types = [];
                                if ($contractor->is_supplier) $types[] = 'Dostawca';
                                if ($contractor->is_customer) $types[] = 'Odbiorca';
                            @endphp
                            {{ implode(', ', $types) ?: '—' }}
                        </x-ui.table.cell>
                        <x-ui.table.cell align="right">
                            <div class="flex items-center justify-end gap-2">
                                <x-ui.button as="a" :href="route('warehouse.contractors.edit', $contractor)" variant="outline" size="sm">Edytuj</x-ui.button>
                                <form method="POST" action="{{ route('warehouse.contractors.destroy', $contractor) }}" onsubmit="return confirm('Usunąć kontrahenta?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="outline-danger" size="sm">Usuń</x-ui.button>
                                </form>
                            </div>
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @empty
                    <x-ui.table.row>
                        <x-ui.table.cell colspan="5" class="text-center text-sm text-gray-500 dark:text-gray-400">
                            Brak kontrahentów.
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforelse
            </x-ui.table>
        </x-ui.card>

        {{ $contractors->links() }}
    </div>
@endsection
