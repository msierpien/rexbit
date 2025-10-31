@props([
    'label',
    'value',
    'trend' => null,
    'trendVariant' => 'success',
    'icon' => null,
])

@php
    $trendColors = [
        'success' => 'text-green-600 dark:text-green-400',
        'warning' => 'text-yellow-600 dark:text-yellow-400',
        'danger' => 'text-red-600 dark:text-red-400',
        'neutral' => 'text-gray-500 dark:text-gray-400',
    ];
@endphp

<x-ui.card :attributes="$attributes->class('p-6')" padding="p-0">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
            <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ $value }}</p>
            @if ($trend)
                <p class="mt-1 text-xs {{ $trendColors[$trendVariant] ?? $trendColors['neutral'] }}">{{ $trend }}</p>
            @endif
        </div>
        @if ($icon)
            <div class="rounded-full bg-blue-100 p-2 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300">
                <x-dynamic-component :component="$icon" class="h-6 w-6" />
            </div>
        @endif
    </div>
</x-ui.card>
