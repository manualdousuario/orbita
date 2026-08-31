<x-layouts.app :title="$meta['title']" :description="$meta['description'] ?? null"
               :canonical="\App\Support\Url::canonicalWithPage()">
    @push('head')
        <x-meta-og :meta="$meta" />
        <x-pagination-head :total="$total" :per-page="(int) config('orbita.posts.per_page', 25)" />
    @endpush

    <div class="space-y-6">
        <header class="border-b border-gray-200 pb-4 dark:border-gray-800">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">#{{ $tag->name }}</h1>
            <p class="mt-1 font-mono text-sm text-gray-500 dark:text-gray-400">
                {{ trans_choice('{0}Nenhum post|{1}:count post|[2,*]:count posts', $total, ['count' => $total]) }}
                <span aria-hidden="true">&middot;</span>
                <a href="{{ route('feed.tag', ['slug' => $tag->slug]) }}" class="hover:text-primary-600 dark:hover:text-primary-400">RSS</a>
            </p>
        </header>

        <livewire:tag-posts :tag-id="$tag->id" />
    </div>
</x-layouts.app>
