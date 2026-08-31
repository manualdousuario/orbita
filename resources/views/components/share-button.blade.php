@props([
    'url',
    'title' => '',
    'compact' => false,
])

<span x-data="shareButton({ url: @js($url), title: @js($title) })" class="inline-flex items-center">
    <button type="button" x-on:click="share()"
            aria-label="Compartilhar post"
            @class([
                'inline-flex items-center gap-1.5 rounded-md text-sm font-medium text-gray-500 transition dark:text-gray-400',
                'justify-center bg-gray-100 p-3.5 hover:bg-gray-100 hover:text-gray-900 dark:bg-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-100' => $compact,
                'py-3 hover:text-primary-600 dark:hover:text-primary-400' => ! $compact,
            ])>
        <x-heroicon-o-share class="h-4 w-4" aria-hidden="true" />
    </button>

    <span x-text="status" x-show="status" x-cloak aria-live="polite"
          class="ml-1 font-mono text-sm text-gray-500 dark:text-gray-400"></span>
</span>
