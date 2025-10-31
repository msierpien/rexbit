@props(['class' => ''])

<tr {{ $attributes->merge(['class' => $class]) }}>
    {{ $slot }}
</tr>
