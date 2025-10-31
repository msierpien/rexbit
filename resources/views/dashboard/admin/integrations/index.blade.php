@extends('layouts.dashboard')

@section('title', 'Integracje')
@section('header', 'Integracje')
@section('subheading', 'Zarządzaj połączeniami z zewnętrznymi systemami')

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Twoje integracje</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Dodawaj połączenia do zewnętrznych systemów i zarządzaj ich konfiguracją.
                </p>
            </div>

            <x-ui.button as="a" :href="route('integrations.create')">
                Dodaj integrację
            </x-ui.button>
        </div>

        <x-ui.card>
            <form method="GET" action="{{ route('integrations.index') }}" class="flex flex-wrap items-center gap-3">
                <x-ui.select
                    label="Filtruj po typie"
                    name="type"
                    :value="$type"
                    :options="collect($types)->mapWithKeys(fn ($typeEnum) => [$typeEnum->value => ucfirst($typeEnum->value)])->prepend('Wszystkie', '')->all()"
                />

                <x-ui.button type="submit" variant="secondary">
                    Zastosuj filtr
                </x-ui.button>
            </form>
        </x-ui.card>

        @if (session('status'))
            <x-ui.alert variant="success">
                {{ session('status') }}
            </x-ui.alert>
        @endif

        @error('integration')
            <x-ui.alert variant="danger">
                {{ $message }}
            </x-ui.alert>
        @enderror

        <x-ui.card>
            <x-ui.table>
                <x-slot name="head">
                    <x-ui.table.head>
                        <x-ui.table.heading>Oznaczenie</x-ui.table.heading>
                        <x-ui.table.heading>Nazwa</x-ui.table.heading>
                        <x-ui.table.heading>Typ</x-ui.table.heading>
                        <x-ui.table.heading>Status</x-ui.table.heading>
                        <x-ui.table.heading>Ostatnia synchronizacja</x-ui.table.heading>
                        <x-ui.table.heading align="right">Akcje</x-ui.table.heading>
                    </x-ui.table.head>
                </x-slot>

                @forelse ($integrations as $integration)
                    <x-ui.table.row>
                        <x-ui.table.cell>
                            {{ strtoupper($integration->type->value) }}-{{ str_pad($integration->user_id, 3, '0', STR_PAD_LEFT) }}-{{ str_pad($loop->iteration, 3, '0', STR_PAD_LEFT) }}
                        </x-ui.table.cell>
                        <x-ui.table.cell class="font-medium text-gray-900 dark:text-gray-100">
                            {{ $integration->name }}
                        </x-ui.table.cell>
                        <x-ui.table.cell>
                            <x-ui.badge variant="primary">
                                {{ ucfirst($integration->type->value) }}
                            </x-ui.badge>
                        </x-ui.table.cell>
                        <x-ui.table.cell>
                            @php
                                $statusVariant = match ($integration->status->value) {
                                    'active' => 'success',
                                    'error' => 'danger',
                                    default => 'neutral',
                                };
                            @endphp
                            <x-ui.badge :variant="$statusVariant">
                                {{ $integration->status->value }}
                            </x-ui.badge>
                        </x-ui.table.cell>
                        <x-ui.table.cell>
                            {{ $integration->last_synced_at?->diffForHumans() ?? 'Brak danych' }}
                        </x-ui.table.cell>
                        <x-ui.table.cell align="right">
                            <div class="flex items-center justify-end gap-2">
                                <x-ui.button as="a" :href="route('integrations.edit', $integration)" variant="outline" size="sm">
                                    Edytuj
                                </x-ui.button>
                                <form method="POST" action="{{ route('integrations.test', $integration) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm">
                                        Testuj
                                    </x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('integrations.destroy', $integration) }}" onsubmit="return confirm('Czy na pewno chcesz usunąć tę integrację?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="outline-danger" size="sm">
                                        Usuń
                                    </x-ui.button>
                                </form>
                            </div>
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @empty
                    <x-ui.table.row>
                        <x-ui.table.cell colspan="6" class="text-center text-sm text-gray-500 dark:text-gray-400">
                            Brak integracji. Dodaj swoją pierwszą integrację, aby rozpocząć.
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforelse
            </x-ui.table>
        </x-ui.card>
    </div>
@endsection
