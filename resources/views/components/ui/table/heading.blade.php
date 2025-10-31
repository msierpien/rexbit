@props(['align' => 'left'])

@php
    $alignment = [
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
    ][$align] ?? 'text-left';
@endphp

<th {{ $attributes->merge(['class' => "px-6 py-3 font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 {$alignment}"]) }}>
    {{ $slot }}
</th>
