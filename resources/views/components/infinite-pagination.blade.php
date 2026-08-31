@props([
    'paginator',
    'pageName' => 'page',
    'island' => 'rows',
    'method' => 'loadMore',
    'noun' => 'itens',
])

{{ $paginator->onEachSide(1)->links() }}

<p wire:key="infinite-status-{{ $pageName }}" role="status" aria-live="polite" class="sr-only">
    Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }} carregada,
    {{ $paginator->count() }} de {{ $paginator->total() }} {{ $noun }}.
</p>

@if ($paginator->hasMorePages())
    <div wire:key="infinite-more-{{ $pageName }}-{{ $paginator->currentPage() }}"
         x-data="infiniteMore" class="js-only justify-center py-6">
        <button type="button" x-ref="more"
                wire:click="{{ $method }}('{{ $pageName }}')"
                wire:island.append="{{ $island }}"
                wire:loading.attr="disabled" wire:target="{{ $method }}"
                class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
            <span wire:loading.remove wire:target="{{ $method }}">Carregar mais</span>
            <span wire:loading wire:target="{{ $method }}" class="inline-flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                Carregando…
            </span>
        </button>
    </div>
@endif
