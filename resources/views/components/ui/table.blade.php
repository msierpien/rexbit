<div {{ $attributes->class('overflow-x-auto') }}>
    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-900/50">
            {{ $head }}
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
            {{ $slot }}
        </tbody>
    </table>
</div>
