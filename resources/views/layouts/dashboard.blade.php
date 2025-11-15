<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Panel') - {{ config('app.name', 'RexBit') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.jsx'])

    @stack('styles')
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen">
    @php
        $user = auth()->user();
        $roleValue = $user?->role?->value;

        $navItems = [
            [
                'type' => 'link',
                'label' => 'Strona główna',
                'icon' => 'home',
                'route' => route('home'),
                'active' => request()->routeIs('home'),
            ],
            [
                'type' => 'link',
                'label' => 'Panel administratora',
                'icon' => 'shield-check',
                'route' => route('dashboard.admin'),
                'active' => request()->routeIs('dashboard.admin'),
                'visible' => $roleValue === 'admin',
            ],
            [
                'type' => 'group',
                'label' => 'Produkty',
                'icon' => 'box',
                'visible' => in_array($roleValue, ['admin', 'user'], true),
                'items' => [
                    [
                        'label' => 'Katalogi',
                        'route' => route('product-catalogs.index'),
                        'active' => request()->routeIs('product-catalogs.*'),
                    ],
                    [
                        'label' => 'Lista produktów',
                        'route' => route('products.index'),
                        'active' => request()->routeIs('products.*'),
                    ],
                    [
                        'label' => 'Producenci',
                        'route' => route('manufacturers.index'),
                        'active' => request()->routeIs('manufacturers.*'),
                    ],
                    [
                        'label' => 'Kategorie',
                        'route' => route('product-categories.index'),
                        'active' => request()->routeIs('product-categories.*'),
                    ],
                    [
                        'label' => 'Ustawienia',
                        'route' => route('products.settings'),
                        'active' => request()->routeIs('products.settings'),
                    ],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Magazyn',
                'icon' => 'warehouse',
                'visible' => in_array($roleValue, ['admin', 'user'], true),
                'items' => [
                    [
                        'label' => 'Dokumenty magazynowe',
                        'route' => route('warehouse.documents.index'),
                        'active' => request()->routeIs('warehouse.documents.*'),
                    ],
                    [
                        'label' => 'Dostawy',
                        'route' => route('warehouse.deliveries.index'),
                        'active' => request()->routeIs('warehouse.deliveries.*'),
                    ],
                    [
                        'label' => 'Kontrahenci',
                        'route' => route('warehouse.contractors.index'),
                        'active' => request()->routeIs('warehouse.contractors.*'),
                    ],
                    [
                        'label' => 'Ustawienia',
                        'route' => route('warehouse.settings'),
                        'active' => request()->routeIs('warehouse.settings'),
                    ],
                ],
            ],
            [
                'type' => 'link',
                'label' => 'Integracje',
                'icon' => 'link',
                'route' => route('integrations.index'),
                'active' => request()->routeIs('integrations.*'),
                'visible' => in_array($roleValue, ['admin', 'user'], true),
            ],
            [
                'type' => 'link',
                'label' => 'Panel użytkownika',
                'icon' => 'user',
                'route' => route('dashboard.user'),
                'active' => request()->routeIs('dashboard.user'),
                'visible' => in_array($roleValue, ['admin', 'user'], true),
            ],
        ];

        $navItems = array_filter($navItems, fn ($item) => $item['visible'] ?? true);

        $brandSubtitle = $user
            ? 'Panel ' . ($roleValue === 'admin' ? 'administratora' : 'użytkownika')
            : null;

        $pageTitle = trim($__env->yieldContent('header', 'Panel'));
        $rawSubtitle = trim($__env->yieldContent('subheading'));
        $pageSubtitle = $rawSubtitle !== '' ? $rawSubtitle : null;
    @endphp

    <div class="min-h-screen flex">
        <x-ui.sidebar
            :brand="config('app.name', 'RexBit')"
            :brandRoute="route('home')"
            :brandSubtitle="$brandSubtitle"
        >
            @foreach ($navItems as $item)
                @if (($item['visible'] ?? true) === false)
                    @continue
                @endif

                @if ($item['type'] === 'group')
                    @php
                        $groupActive = collect($item['items'])->contains(fn ($sub) => $sub['active']);
                    @endphp
                    <x-ui.sidebar.group :label="$item['label']" :icon="$item['icon']" :active="$groupActive">
                        @foreach ($item['items'] as $subItem)
                            <x-ui.sidebar.item
                                :label="$subItem['label']"
                                :href="$subItem['route']"
                                icon="dot"
                                :active="$subItem['active']"
                            />
                        @endforeach
                    </x-ui.sidebar.group>
                @else
                    <x-ui.sidebar.item
                        :label="$item['label']"
                        :href="$item['route']"
                        :icon="$item['icon']"
                        :active="$item['active']"
                    />
                @endif
            @endforeach

            <x-slot:footer>
                @auth
                    <div class="flex items-center gap-3">
                        <x-ui.avatar :name="$user->name" size="md" />
                        <div class="text-sm">
                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $user->name }}</p>
                            <p class="text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::title($roleValue ?? 'unknown') }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Status: {{ $user->status?->value ?? 'unknown' }}</p>
                        </div>
                    </div>
                @endauth
            </x-slot:footer>
        </x-ui.sidebar>

        <div class="flex-1 flex flex-col">
            <x-ui.topbar :title="$pageTitle" :subtitle="$pageSubtitle">
                <x-slot:actions>
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-ui.button type="submit" variant="danger">
                                Wyloguj
                            </x-ui.button>
                        </form>
                    @endauth
                </x-slot:actions>
            </x-ui.topbar>

            <main class="flex-1 p-6">
                @if (session('status'))
                    <x-ui.alert class="mb-4" variant="success">
                        {{ session('status') }}
                    </x-ui.alert>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    @stack('scripts')
</body>
</html>
