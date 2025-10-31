@props([
    'label',
    'icon' => null,
    'active' => false,
])

@php
    $groupId = 'sidebar-group-'.\Illuminate\Support\Str::uuid();
    $buttonClasses = 'flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-left transition text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700';
    $iconClasses = 'h-5 w-5';
@endphp

<button type="button"
        class="{{ $buttonClasses }} w-full"
        data-collapse-toggle="{{ $groupId }}"
        aria-expanded="{{ $active ? 'true' : 'false' }}">
    <span class="flex items-center gap-3">
        @if ($icon)
            @switch($icon)
                @case('box')
                    <svg class="{{ $iconClasses }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5v9a2.25 2.25 0 0 1-2.25 2.25h-12a2.25 2.25 0 0 1-2.25-2.25v-9" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75h6" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 4.5h4.5a.75.75 0 0 1 .75.75v2.25h-6V5.25a.75.75 0 0 1 .75-.75Z" />
                    </svg>
                    @break
                @case('warehouse')
                    <svg class="{{ $iconClasses }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 9 12 3l9.75 6v10.5a1.5 1.5 0 0 1-1.5 1.5h-15a1.5 1.5 0 0 1-1.5-1.5V9Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 22.5V12h6v10.5" />
                    </svg>
                    @break
                @default
                    <span class="{{ $iconClasses }}"></span>
            @endswitch
        @endif
        <span>{{ $label }}</span>
    </span>
    <svg class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
    </svg>
</button>
<div id="{{ $groupId }}" class="mt-1 space-y-1 {{ $active ? '' : 'hidden' }}">
    {{ $slot }}
</div>
