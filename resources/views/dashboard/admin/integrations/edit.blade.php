@extends('layouts.dashboard')

@section('title', 'Edycja integracji')
@section('header', 'Edycja integracji')
@section('subheading', 'Aktualizuj konfigurację połączenia z zewnętrznym systemem')

@section('content')
    <div class="max-w-3xl space-y-6">
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

        <x-ui.card title="{{ $integration->name }}" :subtitle="'Typ: ' . ucfirst($integration->type->value)">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</dt>
                    <dd class="mt-1">
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
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Ostatnia synchronizacja</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $integration->last_synced_at?->diffForHumans() ?? 'Brak danych' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Utworzono</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $integration->created_at->diffForHumans() }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Ostatnia aktualizacja</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $integration->updated_at->diffForHumans() }}
                    </dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card title="Konfiguracja integracji">
            <form method="POST" action="{{ route('integrations.update', $integration) }}" class="space-y-6" id="integration-edit-form">
                @csrf
                @method('PUT')

                <x-ui.input
                    label="Nazwa integracji"
                    name="name"
                    :value="old('name', $integration->name)"
                    required
                />

                <x-ui.input
                    label="Opis"
                    name="description"
                    :value="old('description', $integration->config['description'] ?? '')"
                    placeholder="Opcjonalny opis integracji"
                />

                @if ($integration->type->value === \App\Enums\IntegrationType::PRESTASHOP->value)
                    <div data-driver-fields="prestashop" class="space-y-6">
                        <x-ui.input
                            label="Adres podstawowy sklepu (Base URL)"
                            name="base_url"
                            :value="old('base_url', $integration->config['base_url'] ?? '')"
                            placeholder="https://twoj-sklep.pl"
                        />

                        <x-ui.input
                            label="Klucz API"
                            name="api_key"
                            :value="old('api_key')"
                            placeholder="Pozostaw puste aby zachować obecny klucz"
                        />

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Pozostaw pole puste, aby zachować obecny klucz API.
                        </p>
                    </div>
                @else
                    <x-ui.alert variant="info">
                        Ta integracja wykorzystuje profile importu CSV/XML. Skonfiguruj źródła oraz mapowanie w sekcji poniżej.
                    </x-ui.alert>
                @endif

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">
                        Zapisz zmiany
                    </x-ui.button>

                    <x-ui.button as="a" :href="route('integrations.index')" variant="ghost">
                        Wróć do listy
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>

        @if ($integration->type === \App\Enums\IntegrationType::CSV_XML_IMPORT)
            @include('dashboard.admin.integrations.partials.csv-import-profiles', ['integration' => $integration])
        @endif
    </div>
@endsection
