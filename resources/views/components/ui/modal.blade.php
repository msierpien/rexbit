@props([
    'id',
    'title' => null,
    'size' => 'md',
    'closeLabel' => 'Zamknij okno',
])

@php
    $sizes = [
        'sm' => 'max-w-md',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
    ];

    $wrapperSize = $sizes[$size] ?? $sizes['md'];
@endphp

<div id="{{ $id }}"
     tabindex="-1"
     aria-hidden="true"
     class="fixed inset-0 z-50 hidden h-screen w-full overflow-y-auto overflow-x-hidden bg-gray-900/50 p-4 backdrop-blur-sm">
    <div class="relative mx-auto flex w-full items-center justify-center">
        <div class="relative w-full {{ $wrapperSize }} max-h-full">
            <div class="relative rounded-lg bg-white shadow-lg dark:bg-gray-800">
                <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
                    @if ($title)
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $title }}
                        </h3>
                    @endif
                    <button type="button"
                            class="inline-flex items-center rounded-lg p-1 text-sm text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                            data-modal-hide="{{ $id }}"
                            aria-label="{{ $closeLabel }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4 p-6">
                    {{ $slot }}
                </div>

                @isset($footer)
                    <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                        {{ $footer }}
                    </div>
                @endisset
            </div>
        </div>
    </div>
</div>
