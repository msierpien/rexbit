@props([
    'name',
    'size' => 'lg',
])

@php
    $sizes = [
        'sm' => 'h-8 w-8 text-sm',
        'md' => 'h-10 w-10 text-base',
        'lg' => 'h-12 w-12 text-lg',
    ];

    $classes = implode(' ', [
        'flex items-center justify-center rounded-full bg-blue-100 text-blue-600 font-semibold dark:bg-blue-900 dark:text-blue-200',
        $sizes[$size] ?? $sizes['md'],
    ]);

    $initials = collect(explode(' ', $name))
        ->map(fn ($segment) => mb_substr($segment, 0, 1))
        ->join('');
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    {{ strtoupper($initials) }}
</div>
