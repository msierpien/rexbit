@extends('layouts.dashboard')

@section('title', 'Nowa integracja')
@section('header', 'Dodaj integrację')
@section('subheading', 'Wybierz typ i skonfiguruj połączenie z zewnętrznym systemem')

@section('content')
    <div class="max-w-3xl">
        <x-ui.card>
            <form method="POST" action="{{ route('integrations.store') }}" enctype="multipart/form-data" class="space-y-6" id="integration-create-form">
                @csrf

                <x-ui.select
                    label="Typ integracji"
                    name="type"
                    :value="old('type', request('type', 'prestashop'))"
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
                    label="Opis"
                    name="description"
                    :value="old('description')"
                    placeholder="Opcjonalny opis integracji"
                />

                <div data-driver-fields="prestashop" class="space-y-6">
                    <x-ui.input
                        label="Adres podstawowy sklepu (Base URL)"
                        name="base_url"
                        :value="old('base_url')"
                        placeholder="https://twoj-sklep.pl"
                    />

                    <x-ui.input
                        label="Klucz API"
                        name="api_key"
                        :value="old('api_key')"
                        placeholder="Wprowadź klucz API z Prestashop"
                    />
                </div>

                <div data-driver-fields="csv-xml-import" class="space-y-3 hidden">
                    <x-ui.alert variant="info">
                        Po zapisaniu integracji możesz skonfigurować profile importu CSV/XML, źródła plików oraz mapowanie danych.
                    </x-ui.alert>
                </div>

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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('integration-create-form');
            const typeSelect = form.querySelector('select[name="type"]');
            const sections = form.querySelectorAll('[data-driver-fields]');
            const defaultType = @json(old('type', request('type', 'prestashop')));

            const toggleSections = (type) => {
                sections.forEach((section) => {
                    const driver = section.getAttribute('data-driver-fields');
                    section.classList.toggle('hidden', driver !== type);
                });
            };

            toggleSections(typeSelect.value || defaultType);

            typeSelect.addEventListener('change', (event) => {
                toggleSections(event.target.value);
            });
        });
    </script>
@endpush
