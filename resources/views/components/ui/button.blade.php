@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'icon' => null,
    'iconPosition' => 'left',
    'as' => 'button',
    'href' => null,
])

@php
    $variants = [
        'primary' => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
        'secondary' => 'bg-gray-100 text-gray-700 hover:bg-gray-200 focus:ring-gray-400 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 dark:focus:ring-gray-500',
        'outline' => 'border border-gray-200 text-gray-700 hover:border-blue-500 hover:text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:text-gray-300 dark:hover:border-blue-400 dark:hover:text-blue-300',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
        'outline-danger' => 'border border-red-200 text-red-600 hover:border-red-500 hover:text-red-700 focus:ring-red-500 dark:border-red-900 dark:text-red-300 dark:hover:border-red-700 dark:hover:text-red-200',
        'ghost' => 'text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-2.5 text-base',
    ];

    $baseClasses = 'inline-flex items-center gap-2 rounded-md font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-900';

    $classes = implode(' ', [
        $baseClasses,
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
        $attributes->get('class'),
    ]);
@endphp

@php
    $tag = $as === 'a' ? 'a' : 'button';
    $componentAttributes = $attributes->merge(['class' => $classes]);

    if ($tag === 'button') {
        $componentAttributes = $componentAttributes->merge(['type' => $type]);
    } elseif ($href) {
        $componentAttributes = $componentAttributes->merge(['href' => $href]);
    }
@endphp

<{{ $tag }} {{ $componentAttributes }}>
@if ($icon && $iconPosition === 'left')
    <x-dynamic-component :component="$icon" class="h-4 w-4" />
@endif
{{ $slot }}
@if ($icon && $iconPosition === 'right')
    <x-dynamic-component :component="$icon" class="h-4 w-4" />
@endif
</{{ $tag }}>
