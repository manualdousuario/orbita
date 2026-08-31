@if ($paginator->hasPages())
    @php $canonical = fn (?string $url) => \App\Support\Url::canonicalPage($url, $paginator->getPageName()); @endphp
    <nav data-pagination-nav role="navigation" aria-label="Navegação de páginas"
         class="js-hidden flex items-center justify-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center rounded-md border border-gray-300 px-3 py-3 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-600" aria-disabled="true">
                <x-heroicon-o-chevron-left class="h-4 w-4" aria-hidden="true" />
                <span class="sr-only">Página anterior</span>
            </span>
        @else
            <a href="{{ $canonical($paginator->previousPageUrl()) }}" rel="prev" aria-label="Página anterior"
               class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <x-heroicon-o-chevron-left class="h-4 w-4" aria-hidden="true" />
                <span class="ml-1.5 hidden sm:inline">Anterior</span>
            </a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="inline-flex items-center border border-transparent px-1 py-3 text-sm text-gray-500 dark:text-gray-600">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page === $paginator->currentPage())
                        <span class="inline-flex items-center rounded-md border border-primary-600 bg-primary-600 px-3 py-3 text-sm font-semibold text-white" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $canonical($url) }}" aria-label="Página {{ $page }}"
                           class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Próxima página"
               class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <span class="mr-1.5 hidden sm:inline">Próximo</span>
                <x-heroicon-o-chevron-right class="h-4 w-4" aria-hidden="true" />
            </a>
        @else
            <span class="inline-flex items-center rounded-md border border-gray-300 px-3 py-3 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-600" aria-disabled="true">
                <x-heroicon-o-chevron-right class="h-4 w-4" aria-hidden="true" />
                <span class="sr-only">Próxima página</span>
            </span>
        @endif
    </nav>
@endif
