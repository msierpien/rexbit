@props([
    'label',
    'href' => '#',
    'icon' => null,
    'active' => false,
    'visible' => true,
])

@if ($visible)
    @php
        $baseClasses = 'flex items-center gap-3 rounded-lg px-3 py-2 transition';
        $stateClasses = $active
            ? 'bg-blue-600 text-white shadow-sm'
            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700';

        $iconClasses = 'h-5 w-5';
    @endphp

    <a href="{{ $href }}" {{ $attributes->class("{$baseClasses} {$stateClasses}") }}>
        @if ($icon)
            @switch($icon)
                @case('home')
                    <svg class="{{ $iconClasses }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12m-1.5 0v8.25c0 .621-.504 1.125-1.125 1.125H4.875A1.125 1.125 0 0 1 3.75 20.25V12" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 21V12h6v9" />
                    </svg>
                    @break

                @case('shield-check')
                    <svg class="{{ $iconClasses }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15l3.75-3.75" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12c0 7.098 4.777 9.478 8.284 10.692.484.169.997.169 1.482 0 3.507-1.214 8.284-3.594 8.284-10.692 0-5.227-3.138-7.82-5.768-9.095-1.426-.688-3.183-.688-4.608 0C5.388 4.18 2.25 6.773 2.25 12Z" />
                    </svg>
                    @break

                @case('user')
                    <svg class="{{ $iconClasses }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.106a7.5 7.5 0 0 1 15 0A17.933 17.933 0 0 1 12 21.75c-2.68 0-5.22-.588-7.5-1.644Z" />
                    </svg>
                    @break

                @case('users')
                    <svg class="{{ $iconClasses }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.75a6 6 0 1 0-12 0" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 9a3 3 0 1 0-6 0" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 19.644A4.5 4.5 0 0 0 17.25 12" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25a2.25 2.25 0 1 0-4.5 0 2.25 2.25 0 0 0 4.5 0Z" />
                    </svg>
                    @break

                @default
                    {{ $icon }}
            @endswitch
        @endif

        <span>{{ $label }}</span>
    </a>
@endif
