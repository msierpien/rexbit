@props([
    'brand' => config('app.name', 'RexBit'),
    'brandRoute' => '/',
    'brandSubtitle' => null,
    'navClass' => 'space-y-1',
])

<aside {{ $attributes->class('flex flex-col w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700') }}>
    <div class="px-6 py-6 border-b border-gray-200 dark:border-gray-700">
        <a href="{{ $brandRoute }}" class="text-2xl font-bold text-blue-600">
            {{ $brand }}
        </a>

        @if ($brandSubtitle)
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $brandSubtitle }}
            </p>
        @endif
    </div>

    <nav class="flex-1 px-4 py-6 text-sm font-medium {{ $navClass }}">
        {{ $slot }}
    </nav>

    @isset($footer)
        <div class="px-4 py-6 border-t border-gray-200 dark:border-gray-700">
            {{ $footer }}
        </div>
    @endisset
</aside>
