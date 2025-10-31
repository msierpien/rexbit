@extends('layouts.dashboard')

@section('title', 'Ustawienia magazynu')
@section('header', 'Ustawienia magazynu')
@section('subheading', 'Konfiguruj magazyny oraz zasady numeracji dokumentów')

@section('content')
    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert variant="danger" class="mb-4">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <x-ui.card title="Magazyny">
        <x-slot:actions>
            <x-ui.button
                type="button"
                size="sm"
                data-modal-target="createWarehouseModal"
                data-modal-toggle="createWarehouseModal"
            >
                Dodaj magazyn
            </x-ui.button>
        </x-slot:actions>

        @if ($errors->location->any())
            <x-ui.alert variant="danger" class="mb-4">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->location->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <div class="space-y-3">
            @forelse ($warehouses as $warehouse)
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $warehouse->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Kod: {{ $warehouse->code ?? 'brak' }} |
                                Domyślny: {{ $warehouse->is_default ? 'tak' : 'nie' }} |
                                Ścisła kontrola: {{ $warehouse->strict_control ? 'tak' : 'nie' }}
                            </p>
                        </div>

                        <x-ui.button variant="outline" size="sm">Edytuj</x-ui.button>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($warehouse->catalogs as $catalog)
                            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-200">
                                {{ $catalog->name }}
                            </span>
                        @empty
                            <span class="text-xs text-gray-500 dark:text-gray-400">Brak przypisanych katalogów (dostęp do wszystkich).</span>
                        @endforelse
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Brak zdefiniowanych magazynów. Dodaj pierwszy magazyn, aby rozpocząć pracę.</p>
            @endforelse
        </div>
    </x-ui.card>

    <x-ui.card title="Numeracja dokumentów" class="mt-6" subtitle="Zdefiniuj prefiksy oraz kolejne numery dla poszczególnych typów dokumentów">
        <form method="POST" action="{{ route('warehouse.settings.update') }}" class="space-y-6">
            @csrf
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Typ</th>
                            <th class="px-4 py-3 text-left font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Prefiks</th>
                            <th class="px-4 py-3 text-left font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Sufiks</th>
                            <th class="px-4 py-3 text-left font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Kolejny numer</th>
                            <th class="px-4 py-3 text-left font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Wiodące zera</th>
                            <th class="px-4 py-3 text-left font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Reset</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                        @foreach ($documentTypes as $type)
                            @php
                                $setting = $settings[$type] ?? null;
                                $name = "document_settings[$type]";
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $type }}</td>
                                <td class="px-4 py-3">
                                    <x-ui.input
                                        name="{{ $name }}[prefix]"
                                        :value="old('document_settings.'.$type.'.prefix', $setting?->prefix)"
                                        :placeholder="'np. '.$type.'/'"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <x-ui.input
                                        name="{{ $name }}[suffix]"
                                        :value="old('document_settings.'.$type.'.suffix', $setting?->suffix)"
                                        :placeholder="'np. /'.now()->format('Y')"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <x-ui.input
                                        type="number"
                                        min="1"
                                        name="{{ $name }}[next_number]"
                                        :value="old('document_settings.'.$type.'.next_number', $setting?->next_number ?? 1)"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <x-ui.input
                                        type="number"
                                        min="1"
                                        max="8"
                                        name="{{ $name }}[padding]"
                                        :value="old('document_settings.'.$type.'.padding', $setting?->padding ?? 4)"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <x-ui.select
                                        name="{{ $name }}[reset_period]"
                                        :value="old('document_settings.'.$type.'.reset_period', $setting?->reset_period ?? 'none')"
                                        :options="['none' => 'Brak', 'daily' => 'Codziennie', 'monthly' => 'Miesięcznie', 'yearly' => 'Rocznie']"
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center gap-3">
                <x-ui.button type="submit">Zapisz ustawienia</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.modal id="createWarehouseModal" title="Dodaj magazyn">
        <form method="POST" action="{{ route('warehouse.settings.locations.store') }}" class="space-y-5" id="createWarehouseForm">
            @csrf

            <x-ui.input
                label="Nazwa magazynu"
                name="location_name"
                :value="old('location_name')"
                required
                :error="$errors->location->first('location_name')"
            />

            <x-ui.input
                label="Kod"
                name="location_code"
                :value="old('location_code')"
                placeholder="np. MW-1"
                :error="$errors->location->first('location_code')"
            />

            <div class="space-y-2">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input
                        type="checkbox"
                        name="location_is_default"
                        value="1"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                        @checked(old('location_is_default'))
                    >
                    <span>Ustaw jako domyślny magazyn</span>
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400">Zastąpi aktualnie domyślny magazyn dla tego konta.</p>
            </div>

            <div class="space-y-2">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input
                        type="checkbox"
                        name="location_strict_control"
                        value="1"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                        @checked(old('location_strict_control'))
                    >
                    <span>Włącz ścisłą kontrolę stanów magazynowych</span>
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400">W trybie ścisłej kontroli każda zmiana stanu towaru wymaga dokumentu magazynowego.</p>
            </div>

            <div class="space-y-2">
                <label for="location_catalogs" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Katalogi produktów
                </label>
                <select
                    id="location_catalogs"
                    name="location_catalogs[]"
                    multiple
                    size="5"
                    class="block w-full rounded-lg border border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                >
                    @php
                        $selectedCatalogs = collect(old('location_catalogs', []))->map(fn ($id) => (int) $id)->all();
                    @endphp
                    @foreach ($availableCatalogs as $catalog)
                        <option value="{{ $catalog->id }}" @selected(in_array($catalog->id, $selectedCatalogs, true))>
                            {{ $catalog->name }}
                        </option>
                    @endforeach
                </select>
                @if ($errors->location->has('location_catalogs'))
                    <p class="text-sm text-red-500 dark:text-red-400">{{ $errors->location->first('location_catalogs') }}</p>
                @endif
                <p class="text-xs text-gray-500 dark:text-gray-400">Pozostaw puste, aby magazyn miał dostęp do wszystkich katalogów.</p>
            </div>

            @if ($availableCatalogs->isEmpty())
                <x-ui.alert variant="warning">
                    Nie masz jeszcze żadnych katalogów produktów. Możesz dodawać magazyny bez ograniczeń, a katalogi przypisać później.
                </x-ui.alert>
            @endif
        </form>

        <x-slot:footer>
            <x-ui.button
                type="button"
                variant="ghost"
                data-modal-hide="createWarehouseModal"
            >
                Anuluj
            </x-ui.button>
            <x-ui.button type="submit" form="createWarehouseForm">Zapisz</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
@endsection
