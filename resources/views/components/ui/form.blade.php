{{-- filepath: /Volumes/Dysk 500/projekty/Laravel/rexbit/resources/views/components/ui/form.blade.php --}}
@props([
    'action' => '#',
    'method' => 'POST',
    'enctype' => null,
    'class' => '',
    'title' => null,
    'subtitle' => null,
    'submitText' => 'Wyślij',
    'showSubmit' => true,
    'submitClass' => '',
    'novalidate' => false
])

@php
    $formMethod = strtoupper($method);
    $actualMethod = in_array($formMethod, ['GET', 'POST']) ? $formMethod : 'POST';
    $needsMethodSpoofing = !in_array($formMethod, ['GET', 'POST']);
    
    $defaultFormClasses = 'space-y-6';
    $finalFormClasses = $defaultFormClasses . ' ' . $class;
    
    $defaultSubmitClasses = 'w-full text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 transition-colors duration-200';
    $finalSubmitClasses = $defaultSubmitClasses . ' ' . $submitClass;
@endphp

<div class="max-w-md mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
    @if($title)
        <div class="mb-6 text-center">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $title }}</h2>
            @if($subtitle)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    <form 
        action="{{ $action }}" 
        method="{{ $actualMethod }}"
        class="{{ $finalFormClasses }}"
        @if($enctype) enctype="{{ $enctype }}" @endif
        @if($novalidate) novalidate @endif
        {{ $attributes->except(['action', 'method', 'class', 'enctype', 'novalidate']) }}
    >
        @if($needsMethodSpoofing)
            @method($method)
        @endif
        
        @if($actualMethod === 'POST')
            @csrf
        @endif

        {{ $slot }}

        @if($showSubmit)
            <div class="mt-6">
                <button type="submit" class="{{ $finalSubmitClasses }}">
                    {{ $submitText }}
                </button>
            </div>
        @endif
    </form>
</div>