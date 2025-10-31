@props([
    'title' => 'Panel',
    'subtitle' => null,
])

<header {{ $attributes->class('sticky top-0 z-40 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center justify-between') }}>
    <div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
            {{ $title }}
        </h1>

        @if ($subtitle)
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $subtitle }}
            </p>
        @elseif(trim($slot))
            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $slot }}
            </div>
        @endif
    </div>

    @isset($actions)
        <div class="flex items-center gap-4">
            {{ $actions }}
        </div>
    @endisset
</header>
