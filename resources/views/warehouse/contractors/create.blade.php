@extends('layouts.dashboard')

@section('title', 'Nowy kontrahent')
@section('header', 'Dodaj kontrahenta')
@section('subheading', 'Przechowuj dane dostawców i odbiorców')

@section('content')
    <div class="max-w-3xl">
        <x-ui.card>
            <form method="POST" action="{{ route('warehouse.contractors.store') }}" class="space-y-6">
                @csrf

                <x-ui.input label="Nazwa" name="name" :value="old('name')" required />
                <x-ui.input label="NIP" name="tax_id" :value="old('tax_id')" />
                <x-ui.input label="Email" name="email" :value="old('email')" />
                <x-ui.input label="Telefon" name="phone" :value="old('phone')" />

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <x-ui.input label="Miasto" name="city" :value="old('city')" />
                    <x-ui.input label="Kod pocztowy" name="postal_code" :value="old('postal_code')" />
                    <x-ui.input label="Ulica" name="street" :value="old('street')" class="md:col-span-2" />
                </div>

                <div class="flex items-center gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="is_supplier" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" @checked(old('is_supplier'))>
                        Dostawca
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="is_customer" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" @checked(old('is_customer'))>
                        Odbiorca
                    </label>
                </div>

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">Zapisz kontrahenta</x-ui.button>
                    <x-ui.button as="a" :href="route('warehouse.contractors.index')" variant="ghost">Anuluj</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
