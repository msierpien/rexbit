@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
])

@php
    $selectId = $id ?? $name ?? uniqid('select_');
@endphp

<div {{ $attributes->class('space-y-2') }}>
    @if ($label)
        <label for="{{ $selectId }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $label }}
        </label>
    @endif

    <select
        id="{{ $selectId }}"
        name="{{ $name ?? $selectId }}"
        {{ $attributes->merge([
            'class' => 'block w-full rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100',
        ])->except(['label']) }}
    >
        @if ($placeholder)
            <option value="" disabled selected>{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($optionValue == $value)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    @error($name)
        <p class="text-sm text-red-500 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
