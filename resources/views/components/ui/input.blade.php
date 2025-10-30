{{-- filepath: /Volumes/Dysk 500/projekty/Laravel/rexbit/resources/views/components/ui/input.blade.php --}}
@props([
    'type' => 'text',
    'id' => null,
    'name' => null,
    'label' => null,
    'placeholder' => '',
    'required' => false,
    'disabled' => false,
    'value' => '',
    'error' => null,
    'class' => '',
    'labelClass' => '',
    'inputClass' => '',
    'helpText' => null
])

@php
    $inputId = $id ?? $name ?? uniqid('input_');
    $defaultInputClasses = 'bg-gray-50 border text-gray-900 text-sm rounded-lg block w-full p-2.5 dark:bg-gray-700 dark:placeholder-gray-400 dark:text-white transition-colors duration-200';
    
    if ($error) {
        $borderClasses = 'border-red-500 focus:ring-red-500 focus:border-red-500 dark:border-red-500 dark:focus:ring-red-500 dark:focus:border-red-500';
    } else {
        $borderClasses = 'border-gray-300 focus:ring-blue-500 focus:border-blue-500 dark:border-gray-600 dark:focus:ring-blue-500 dark:focus:border-blue-500';
    }
    
    $finalInputClasses = $defaultInputClasses . ' ' . $borderClasses . ' ' . $inputClass;
    $defaultLabelClasses = 'block mb-2 text-sm font-medium text-gray-900 dark:text-white';
    $finalLabelClasses = $defaultLabelClasses . ' ' . $labelClass;
@endphp

<div {{ $attributes->merge(['class' => $class]) }}>
    @if($label)
        <label for="{{ $inputId }}" class="{{ $finalLabelClasses }}">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <input 
        type="{{ $type }}"
        id="{{ $inputId }}"
        name="{{ $name ?? $inputId }}"
        class="{{ $finalInputClasses }}"
        placeholder="{{ $placeholder }}"
        value="{{ old($name ?? $inputId, $value) }}"
        @if($required) required @endif
        @if($disabled) disabled @endif
        {{ $attributes->except(['class', 'type', 'id', 'name', 'placeholder', 'value', 'required', 'disabled']) }}
    />

    @if($helpText && !$error)
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $helpText }}</p>
    @endif

    @if($error)
        <p class="mt-1 text-sm text-red-500 dark:text-red-400">{{ $error }}</p>
    @endif
</div>