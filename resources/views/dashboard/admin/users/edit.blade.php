@extends('layouts.dashboard')

@section('title', 'Edycja użytkownika')
@section('header', 'Edytuj użytkownika')
@section('subheading', 'Zmień rolę i status konta w systemie')

@section('content')
    <div class="max-w-xl">
        <x-ui.card :title="$user->name" :subtitle="$user->email">
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <x-ui.select
                    label="Rola użytkownika"
                    name="role"
                    :value="old('role', $user->role?->value)"
                    :options="collect($roles)->mapWithKeys(fn ($role) => [$role->value => ucfirst($role->value)])->all()"
                />

                <x-ui.select
                    label="Status użytkownika"
                    name="status"
                    :value="old('status', $user->status?->value)"
                    :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => ucfirst($status->value)])->all()"
                />

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">
                        Zapisz zmiany
                    </x-ui.button>
                    <x-ui.button as="a" :href="route('admin.users.index')" variant="ghost">
                        Anuluj
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
