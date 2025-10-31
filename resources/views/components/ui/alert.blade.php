@props([
    'variant' => 'info',
    'icon' => null,
    'dismissible' => false,
    'onDismiss' => null,
])

@php
    $variants = [
        'info' => 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200',
        'success' => 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200',
        'warning' => 'border-yellow-200 bg-yellow-50 text-yellow-800 dark:border-yellow-900 dark:bg-yellow-950 dark:text-yellow-200',
        'danger' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200',
    ];

    $classes = implode(' ', [
        'rounded-lg border px-4 py-3 text-sm',
        $variants[$variant] ?? $variants['info'],
        $attributes->get('class'),
    ]);
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    <div class="flex items-start gap-3">
        @if ($icon)
            <x-dynamic-component :component="$icon" class="mt-0.5 h-5 w-5" />
        @endif

        <div class="grow">
            {{ $slot }}
        </div>

        @if ($dismissible)
            <button
                type="button"
                class="inline-flex shrink-0 rounded-md p-1 text-current transition hover:bg-black/5 focus:outline-none"
                @if ($onDismiss) {{ $onDismiss }} @endif
            >
                <span class="sr-only">Zamknij</span>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        @endif
    </div>
</div>
