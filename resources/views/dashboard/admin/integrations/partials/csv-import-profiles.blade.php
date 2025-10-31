@php
    use Illuminate\Support\Arr;

    $profileRouteBase = fn ($profile) => [
        'integration' => $integration,
        'profile' => $profile,
    ];

    $catalogOptions = $integration->user->productCatalogs
        ->sortBy('name')
        ->mapWithKeys(fn ($catalog) => [$catalog->id => $catalog->name])
        ->all();

    $productFields = [
        'sku' => 'SKU',
        'name' => 'Nazwa produktu',
        'description' => 'Opis',
        'sale_price_net' => 'Cena sprzedaży netto',
        'sale_vat_rate' => 'VAT sprzedaży (%)',
        'purchase_price_net' => 'Cena zakupu netto',
        'purchase_vat_rate' => 'VAT zakupu (%)',
        'category_slug' => 'Slug kategorii',
        'category_name' => 'Nazwa kategorii',
    ];

    $categoryFields = [
        'slug' => 'Slug kategorii',
        'name' => 'Nazwa kategorii',
        'parent_slug' => 'Slug kategorii nadrzędnej',
        'parent_name' => 'Nazwa kategorii nadrzędnej',
    ];
@endphp

<x-ui.card title="Nowy profil importu" class="border-blue-100 bg-blue-50/40 dark:border-blue-900 dark:bg-blue-950/40">
    <form method="POST" action="{{ route('integrations.import-profiles.store', $integration) }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-6 md:grid-cols-2">
        @csrf

        <x-ui.input label="Nazwa profilu" name="name" :value="old('name')" required />

        <x-ui.select
            label="Format"
            name="format"
            :value="old('format', 'csv')"
            :options="['csv' => 'CSV', 'xml' => 'XML']"
        />

        <x-ui.select
            label="Źródło danych"
            name="source_type"
            :value="old('source_type', 'file')"
            :options="['file' => 'Plik (upload)', 'url' => 'Adres URL']"
        />

        <x-ui.select
            label="Katalog docelowy"
            name="catalog_id"
            :value="old('catalog_id', optional($integration->user->productCatalogs->first())->id)"
            :options="$catalogOptions"
            placeholder="Wybierz katalog produktów"
        />

        @if (empty($catalogOptions))
            <x-ui.alert variant="warning">
                Nie masz jeszcze katalogów produktów – zostanie utworzony domyślny katalog przy zapisie profilu.
            </x-ui.alert>
        @endif

        <x-ui.input
            label="Utwórz nowy katalog (opcjonalnie)"
            name="new_catalog_name"
            :value="old('new_catalog_name')"
            placeholder="np. Import hurtownia"
            help-text="Wpisanie nazwy spowoduje utworzenie i użycie nowego katalogu."
        />

        <x-ui.input
            label="Plik źródłowy"
            name="source_file"
            type="file"
            accept=".csv,.xml,text/csv,application/xml"
        />

        <x-ui.input
            label="Adres URL"
            name="source_url"
            :value="old('source_url')"
            placeholder="https://example.com/export.csv"
        />

        <x-ui.input
            label="Separator kolumn (CSV)"
            name="delimiter"
            :value="old('delimiter', ';')"
            placeholder="np. ;"
        />

        <div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="has_header" value="1" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700" {{ old('has_header', '1') ? 'checked' : '' }}>
                <span>Plik zawiera nagłówek</span>
            </label>
        </div>

        <div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700" {{ old('is_active', '1') ? 'checked' : '' }}>
                <span>Profil aktywny</span>
            </label>
        </div>

        <x-ui.select
            label="Harmonogram"
            name="fetch_mode"
            :value="old('fetch_mode', 'manual')"
            :options="[
                'manual' => 'Ręcznie na żądanie',
                'interval' => 'Co X minut',
                'daily' => 'Codziennie o godzinie',
                'cron' => 'Wyrażenie CRON',
            ]"
        />

        <x-ui.input
            label="Co ile minut"
            type="number"
            name="fetch_interval_minutes"
            min="5"
            :value="old('fetch_interval_minutes')"
        />

        <x-ui.input
            label="Godzina (HH:MM)"
            type="time"
            name="fetch_daily_at"
            :value="old('fetch_daily_at')"
        />

        <x-ui.input
            label="Wyrażenie CRON"
            name="fetch_cron_expression"
            :value="old('fetch_cron_expression')"
            placeholder="np. 0 * * * *"
        />

        <x-ui.input
            label="XPath rekordu (XML)"
            name="options[record_path]"
            :value="old('options.record_path', '')"
            placeholder="Np. /products/product"
        />

        <div class="md:col-span-2 flex items-center gap-3">
            <x-ui.button type="submit">Dodaj profil importu</x-ui.button>
        </div>
    </form>
</x-ui.card>

@foreach ($integration->importProfiles as $profile)
    @php
        $headerOptions = collect($profile->last_headers ?? [])->mapWithKeys(fn ($header) => [$header => $header])->all();
        $productMapping = $profile->mappings->where('target_type', 'product')->pluck('source_field', 'target_field')->toArray();
        $categoryMapping = $profile->mappings->where('target_type', 'category')->pluck('source_field', 'target_field')->toArray();
        $latestRun = $profile->runs->first();
    @endphp

    <x-ui.card title="Profil: {{ $profile->name }}" class="mt-6">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                <p><span class="font-medium">Format:</span> {{ strtoupper($profile->format) }}</p>
                <p><span class="font-medium">Źródło:</span> {{ $profile->source_type === 'file' ? 'Plik' : 'URL' }}</p>
                <p><span class="font-medium">Aktywny:</span> {{ $profile->is_active ? 'Tak' : 'Nie' }}</p>
                <p><span class="font-medium">Tryb harmonogramu:</span> {{ ucfirst($profile->fetch_mode) }}</p>
                <p><span class="font-medium">Następne uruchomienie:</span> {{ $profile->next_run_at?->diffForHumans() ?? 'brak' }}</p>
                <p><span class="font-medium">Ostatnie nagłówki:</span> {{ $profile->last_headers ? implode(', ', $profile->last_headers) : 'brak danych' }}</p>
                @if ($latestRun)
                    <p><span class="font-medium">Ostatnie uruchomienie:</span> {{ $latestRun->created_at->diffForHumans() }} ({{ $latestRun->status }})</p>
                @endif
            </div>

            <div class="flex flex-wrap gap-3 justify-end">
                <form method="POST" action="{{ route('integrations.import-profiles.run', $profileRouteBase($profile)) }}" class="inline">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">Uruchom teraz</x-ui.button>
                </form>

                <form method="POST" action="{{ route('integrations.import-profiles.refresh', $profileRouteBase($profile)) }}" class="inline">
                    @csrf
                    <x-ui.button type="submit" variant="outline" size="sm">Odśwież nagłówki</x-ui.button>
                </form>

                <form method="POST" action="{{ route('integrations.import-profiles.destroy', $profileRouteBase($profile)) }}" class="inline" onsubmit="return confirm('Czy na pewno chcesz usunąć ten profil importu?');">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="outline-danger" size="sm">Usuń</x-ui.button>
                </form>
            </div>
        </div>

        <div class="mt-6 border-t border-gray-200 pt-6 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Aktualizacja profilu</h3>
            <form method="POST" action="{{ route('integrations.import-profiles.update', $profileRouteBase($profile)) }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-6 md:grid-cols-2 mt-4">
                @csrf
                @method('PUT')

                <x-ui.input label="Nazwa profilu" name="name" :value="old('name', $profile->name)" required />

                <x-ui.select
                    label="Format"
                    name="format"
                    :value="old('format', $profile->format)"
                    :options="['csv' => 'CSV', 'xml' => 'XML']"
                />

                <x-ui.select
                    label="Źródło danych"
                    name="source_type"
                    :value="old('source_type', $profile->source_type)"
                    :options="['file' => 'Plik (upload)', 'url' => 'Adres URL']"
                />

                <x-ui.select
                    label="Katalog docelowy"
                    name="catalog_id"
                    :value="old('catalog_id', $profile->catalog_id)"
                    :options="$catalogOptions"
                    placeholder="Wybierz katalog"
                />

                <x-ui.input
                    label="Utwórz nowy katalog (opcjonalnie)"
                    name="new_catalog_name"
                    :value="old('new_catalog_name')"
                    placeholder="np. Import partner"
                />

                <x-ui.input
                    label="Nowy plik (opcjonalnie)"
                    name="source_file"
                    type="file"
                    accept=".csv,.xml,text/csv,application/xml"
                />

                <x-ui.input
                    label="Adres URL"
                    name="source_url"
                    :value="old('source_url', $profile->source_type === 'url' ? $profile->source_location : '')"
                />

                <x-ui.input
                    label="Separator kolumn"
                    name="delimiter"
                    :value="old('delimiter', $profile->delimiter)"
                />

                <div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="has_header" value="1" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700" {{ $profile->has_header ? 'checked' : '' }}>
                        <span>Plik zawiera nagłówek</span>
                    </label>
                </div>

                <div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700" {{ $profile->is_active ? 'checked' : '' }}>
                        <span>Profil aktywny</span>
                    </label>
                </div>

                <x-ui.select
                    label="Harmonogram"
                    name="fetch_mode"
                    :value="old('fetch_mode', $profile->fetch_mode)"
                    :options="[
                        'manual' => 'Ręcznie',
                        'interval' => 'Co X minut',
                        'daily' => 'Codziennie o godzinie',
                        'cron' => 'Wyrażenie CRON',
                    ]"
                />

                <x-ui.input
                    label="Co ile minut"
                    type="number"
                    name="fetch_interval_minutes"
                    min="5"
                    :value="old('fetch_interval_minutes', $profile->fetch_interval_minutes)"
                />

                <x-ui.input
                    label="Godzina (HH:MM)"
                    type="time"
                    name="fetch_daily_at"
                    :value="old('fetch_daily_at', optional($profile->fetch_daily_at)->format('H:i'))"
                />

                <x-ui.input
                    label="Wyrażenie CRON"
                    name="fetch_cron_expression"
                    :value="old('fetch_cron_expression', $profile->fetch_cron_expression)"
                />

                <x-ui.input
                    label="XPath rekordu (XML)"
                    name="options[record_path]"
                    :value="old('options.record_path', Arr::get($profile->options, 'record_path'))"
                />

                <div class="md:col-span-2 flex items-center gap-3">
                    <x-ui.button type="submit">Zapisz profil</x-ui.button>
                </div>
            </form>
        </div>

        <div class="mt-6 border-t border-gray-200 pt-6 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Mapowanie pól</h3>
            @if (empty($headerOptions))
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Brak nagłówków – odśwież nagłówki, aby skonfigurować mapowanie.</p>
            @else
                <form method="POST" action="{{ route('integrations.import-profiles.mappings', $profileRouteBase($profile)) }}" class="mt-4 space-y-4">
                    @csrf

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-3">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Produkty</h4>
                            @foreach ($productFields as $target => $label)
                                <x-ui.select
                                    label="{{ $label }}"
                                    name="product[{{ $target }}]"
                                    :value="$productMapping[$target] ?? old('product.' . $target)"
                                    :options="['' => '— Pomiń —'] + $headerOptions"
                                    placeholder="Wybierz nagłówek"
                                />
                            @endforeach
                        </div>

                        <div class="space-y-3">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kategorie</h4>
                            @foreach ($categoryFields as $target => $label)
                                <x-ui.select
                                    label="{{ $label }}"
                                    name="category[{{ $target }}]"
                                    :value="$categoryMapping[$target] ?? old('category.' . $target)"
                                    :options="['' => '— Pomiń —'] + $headerOptions"
                                    placeholder="Wybierz nagłówek"
                                />
                            @endforeach
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-ui.button type="submit">Zapisz mapowanie</x-ui.button>
                    </div>
                </form>
            @endif
        </div>

        <div class="mt-6 border-t border-gray-200 pt-6 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Historia ostatnich uruchomień</h3>
            <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                @forelse ($profile->runs as $run)
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                        <span>{{ $run->created_at->format('Y-m-d H:i') }} – {{ $run->status }} ({{ $run->success_count }}/{{ $run->processed_count }})</span>
                        @if ($run->message)
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $run->message }}</span>
                        @endif
                    </div>
                @empty
                    <p>Brak uruchomień.</p>
                @endforelse
            </div>
        </div>
    </x-ui.card>
@endforeach
