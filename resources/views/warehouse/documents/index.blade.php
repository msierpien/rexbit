@extends('layouts.dashboard')

@section('title', 'Dokumenty magazynowe')
@section('header', 'Dokumenty magazynowe')
@section('subheading', 'Zarządzaj przepływem towarów w magazynie')

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Dokumenty</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Twórz dokumenty PZ, WZ oraz inne ruchy magazynowe.</p>
            </div>

            <x-ui.button as="a" :href="route('warehouse.documents.create')">Nowy dokument</x-ui.button>
        </div>

        @if (session('status'))
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        @endif

        <x-ui.card>
            <x-ui.table>
                <x-slot name="head">
                    <x-ui.table.head>
                        <x-ui.table.heading>Numer</x-ui.table.heading>
                        <x-ui.table.heading>Typ</x-ui.table.heading>
                    <x-ui.table.heading>Magazyn</x-ui.table.heading>
                    <x-ui.table.heading>Kontrahent</x-ui.table.heading>
                        <x-ui.table.heading>Data</x-ui.table.heading>
                        <x-ui.table.heading>Status</x-ui.table.heading>
                        <x-ui.table.heading align="right">Akcje</x-ui.table.heading>
                    </x-ui.table.head>
                </x-slot>

                @forelse ($documents as $document)
                    <x-ui.table.row>
                        <x-ui.table.cell class="font-medium text-gray-900 dark:text-gray-100">{{ $document->number }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $document->type }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $document->warehouse?->name ?? 'Brak' }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $document->contractor?->name ?? '—' }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $document->issued_at->format('Y-m-d') }}</x-ui.table.cell>
                        <x-ui.table.cell>
                            <x-ui.badge :variant="$document->status === 'posted' ? 'success' : 'neutral'">{{ $document->status }}</x-ui.badge>
                        </x-ui.table.cell>
                        <x-ui.table.cell align="right">
                            <div class="flex items-center justify-end gap-2">
                                <x-ui.button as="a" :href="route('warehouse.documents.edit', $document)" variant="outline" size="sm">Edytuj</x-ui.button>
                                <form method="POST" action="{{ route('warehouse.documents.destroy', $document) }}" onsubmit="return confirm('Usunąć dokument?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="outline-danger" size="sm">Usuń</x-ui.button>
                                </form>
                            </div>
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @empty
                    <x-ui.table.row>
                        <x-ui.table.cell colspan="7" class="text-center text-sm text-gray-500 dark:text-gray-400">
                            Brak dokumentów magazynowych.
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforelse
            </x-ui.table>
        </x-ui.card>

        {{ $documents->links() }}
    </div>
@endsection
