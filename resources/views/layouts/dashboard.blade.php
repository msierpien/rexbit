<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Panel') - {{ config('app.name', 'RexBit') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen">
    <div class="min-h-screen flex">
        <aside class="flex flex-col w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700">
            <div class="px-6 py-6 border-b border-gray-200 dark:border-gray-700">
                <a href="{{ route('home') }}" class="text-2xl font-bold text-blue-600">RexBit</a>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Panel @auth{{ auth()->user()->role?->value === 'admin' ? 'administratora' : 'użytkownika' }}@endauth
                </p>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-1 text-sm font-medium">
                @php
                    $navItems = [
                        [
                            'label' => 'Strona główna',
                            'icon' => 'home',
                            'route' => route('home'),
                            'active' => request()->routeIs('home'),
                        ],
                        [
                            'label' => 'Panel administratora',
                            'icon' => 'shield-check',
                            'route' => route('dashboard.admin'),
                            'active' => request()->routeIs('dashboard.admin'),
                            'visible' => auth()->user()?->role?->value === 'admin',
                        ],
                        [
                            'label' => 'Użytkownicy',
                            'icon' => 'users',
                            'route' => route('admin.users.index'),
                            'active' => request()->routeIs('admin.users.*'),
                            'visible' => auth()->user()?->role?->value === 'admin',
                        ],
                        [
                            'label' => 'Panel użytkownika',
                            'icon' => 'user',
                            'route' => route('dashboard.user'),
                            'active' => request()->routeIs('dashboard.user'),
                            'visible' => in_array(auth()->user()?->role?->value, ['admin', 'user'], true),
                        ],
                    ];

                    $navItems = array_filter($navItems, fn ($item) => $item['visible'] ?? true);
                @endphp

                @foreach ($navItems as $item)
                    <a href="{{ $item['route'] }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition {{ $item['active'] ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                        @switch($item['icon'])
                            @case('home')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12m-1.5 0v8.25c0 .621-.504 1.125-1.125 1.125H4.875A1.125 1.125 0 0 1 3.75 20.25V12" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 21V12h6v9" />
                                </svg>
                                @break
                            @case('shield-check')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15l3.75-3.75" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12c0 7.098 4.777 9.478 8.284 10.692.484.169.997.169 1.482 0 3.507-1.214 8.284-3.594 8.284-10.692 0-5.227-3.138-7.82-5.768-9.095-1.426-.688-3.183-.688-4.608 0C5.388 4.18 2.25 6.773 2.25 12Z" />
                                </svg>
                                @break
                            @case('user')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.106a7.5 7.5 0 0 1 15 0A17.933 17.933 0 0 1 12 21.75c-2.68 0-5.22-.588-7.5-1.644Z" />
                                </svg>
                                @break
                            @case('users')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.75a6 6 0 1 0-12 0" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 9a3 3 0 1 0-6 0" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 19.644A4.5 4.5 0 0 0 17.25 12" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25a2.25 2.25 0 1 0-4.5 0 2.25 2.25 0 0 0 4.5 0Z" />
                                </svg>
                                @break
                        @endswitch
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="px-4 py-6 border-t border-gray-200 dark:border-gray-700">
                @auth
                    <div class="flex items-center gap-3">
                        <x-ui.avatar :name="auth()->user()->name" size="md" />
                        <div class="text-sm">
                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ auth()->user()->name }}</p>
                            <p class="text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::title(auth()->user()->role?->value ?? 'unknown') }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Status: {{ auth()->user()->status?->value ?? 'unknown' }}</p>
                        </div>
                    </div>
                @endauth
            </div>
        </aside>

        <div class="flex-1 flex flex-col">
            <header class="sticky top-0 z-40 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">@yield('header', 'Panel')</h1>
                    @hasSection('subheading')
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">@yield('subheading')</p>
                    @endif
                </div>

                <div class="flex items-center gap-4">
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-ui.button type="submit" variant="danger">
                                Wyloguj
                            </x-ui.button>
                        </form>
                    @endauth
                </div>
            </header>

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
