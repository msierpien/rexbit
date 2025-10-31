@extends('layouts.dashboard')

@section('title', 'Zarządzanie użytkownikami')
@section('header', 'Zarządzanie użytkownikami')
@section('subheading', 'Przeglądaj konta i aktualizuj role oraz statusy w systemie')

@section('content')
    <x-ui.card title="Lista użytkowników" :subtitle="'Łącznie: ' . $users->total()" padding="p-0">
        @if($errors->has('user'))
            <x-ui.alert class="mx-6 mt-4" variant="danger">
                {{ $errors->first('user') }}
            </x-ui.alert>
        @endif

        <div class="px-6 py-4">
            <x-ui.table>
                <x-slot name="head">
                    <x-ui.table.head>
                        <x-ui.table.heading>ID</x-ui.table.heading>
                        <x-ui.table.heading>Nazwa</x-ui.table.heading>
                        <x-ui.table.heading>Email</x-ui.table.heading>
                        <x-ui.table.heading>Rola</x-ui.table.heading>
                        <x-ui.table.heading>Status</x-ui.table.heading>
                        <x-ui.table.heading align="right">Akcje</x-ui.table.heading>
                    </x-ui.table.head>
                </x-slot>

                @forelse ($users as $user)
                    <x-ui.table.row>
                        <x-ui.table.cell class="whitespace-nowrap text-gray-700 dark:text-gray-300">#{{ $user->id }}</x-ui.table.cell>
                        <x-ui.table.cell class="max-w-xs font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</x-ui.table.cell>
                        <x-ui.table.cell class="text-gray-600 dark:text-gray-300">{{ $user->email }}</x-ui.table.cell>
                        <x-ui.table.cell>
                            <x-ui.badge variant="primary">
                                {{ $user->role?->value ?? 'unknown' }}
                            </x-ui.badge>
                        </x-ui.table.cell>
                        <x-ui.table.cell>
                            @php
                                $statusValue = $user->status?->value ?? 'unknown';
                                $statusVariant = match ($statusValue) {
                                    'active' => 'success',
                                    'inactive' => 'neutral',
                                    'suspended' => 'warning',
                                    default => 'neutral',
                                };
                            @endphp
                            <x-ui.badge :variant="$statusVariant">
                                {{ $statusValue }}
                            </x-ui.badge>
                        </x-ui.table.cell>
                        <x-ui.table.cell align="right">
                            <div class="flex items-center justify-end gap-2">
                                <x-ui.button as="a" :href="route('admin.users.edit', $user)" variant="outline" size="sm">
                                    Edytuj
                                </x-ui.button>
                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Czy na pewno chcesz usunąć tego użytkownika?');">
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
                            Brak użytkowników do wyświetlenia.
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforelse
            </x-ui.table>
        </div>

        <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-700">
            {{ $users->withQueryString()->links() }}
        </div>
    </x-ui.card>
@endsection
