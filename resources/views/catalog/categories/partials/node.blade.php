<div class="rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
    <div class="flex items-center justify-between">
        <div>
            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $category->name }}</p>
            @if ($category->children->isNotEmpty())
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $category->children->count() }} podkategorii</p>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <x-ui.button as="a" :href="route('product-categories.edit', $category)" variant="outline" size="sm">Edytuj</x-ui.button>
            <form method="POST" action="{{ route('product-categories.destroy', $category) }}" onsubmit="return confirm('Usunąć tę kategorię?');">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="outline-danger" size="sm">Usuń</x-ui.button>
            </form>
        </div>
    </div>

    @if ($category->children->isNotEmpty())
        <div class="mt-3 space-y-3 border-l border-dashed border-gray-200 pl-4 dark:border-gray-700">
            @foreach ($category->children as $child)
                @include('catalog.categories.partials.node', ['category' => $child])
            @endforeach
        </div>
    @endif
</div>
