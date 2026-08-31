<x-layouts.app :title="$meta['title']" :description="$meta['description']">
    @push('head')
        <x-meta-og :meta="$meta" />
    @endpush

    <article>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $page->title }}</h1>

        <div class="md-content mt-6 text-gray-700 dark:text-gray-300">
            {!! $contentHtml !!}
        </div>

        <hr class="my-6 border-gray-200 dark:border-gray-800">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Atualizada em {{ $page->updated_at?->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y') }}.
        </p>
    </article>
</x-layouts.app>
