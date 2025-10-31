@extends('layouts.dashboard')

@section('title', 'Nowa integracja')
@section('header', 'Dodaj integrację')
@section('subheading', 'Wybierz typ i skonfiguruj połączenie z zewnętrznym systemem')

@section('content')
    <div class="max-w-3xl">
        <x-ui.card>
            <form method="POST" action="{{ route('integrations.store') }}" class="space-y-6">
                @csrf

                <x-ui.select
                    label="Typ integracji"
                    name="type"
                    :value="old('type', request('type'))"
                    :options="collect($types)->mapWithKeys(fn ($type) => [$type->value => ucfirst($type->value)])->all()"
                    placeholder="Wybierz typ integracji"
                />

                <x-ui.input
                    label="Nazwa integracji"
                    name="name"
                    :value="old('name')"
                    placeholder="Np. Sklep Prestashop Polska"
                    required
                />

                <x-ui.input
                    label="Adres podstawowy sklepu (Base URL)"
                    name="base_url"
                    :value="old('base_url')"
                    placeholder="https://twoj-sklep.pl"
                    required
                />

                <x-ui.input
                    label="Klucz API"
                    name="api_key"
                    :value="old('api_key')"
                    placeholder="Wprowadź klucz API z Prestashop"
                    required
                />

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">
                        Zapisz integrację
                    </x-ui.button>
                    <x-ui.button as="a" :href="route('integrations.index')" variant="ghost">
                        Anuluj
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
