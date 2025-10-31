@props(['align' => 'left'])

@php
    $alignment = [
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
    ][$align] ?? 'text-left';
@endphp

<td {{ $attributes->merge(['class' => "px-6 py-4 {$alignment}"]) }}>
    {{ $slot }}
</td>
