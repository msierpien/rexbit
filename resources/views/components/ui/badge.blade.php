@props([
    'variant' => 'default',
    'size' => 'md',
    'icon' => null,
])

@php
    $variants = [
        'primary' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'success' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'warning' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
        'danger' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'neutral' => 'bg-gray-100 text-gray-700 dark:bg-gray-900/40 dark:text-gray-300',
        'default' => 'bg-gray-100 text-gray-700 dark:bg-gray-900/40 dark:text-gray-300',
    ];

    $sizes = [
        'sm' => 'px-2 py-0.5 text-[11px]',
        'md' => 'px-3 py-1 text-xs',
        'lg' => 'px-4 py-1 text-sm',
    ];

    $classes = implode(' ', [
        'inline-flex items-center rounded-full font-semibold uppercase tracking-wide',
        $sizes[$size] ?? $sizes['md'],
        $variants[$variant] ?? $variants['default'],
        $attributes->get('class'),
    ]);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)
        <x-dynamic-component :component="$icon" class="mr-1 h-3.5 w-3.5" />
    @endif
    {{ $slot }}
</span>
