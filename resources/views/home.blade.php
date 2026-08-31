@php
    $tab ??= \App\Support\HomeFeedTabs::defaultTab();
    $meta = \App\Support\HomeFeedTabs::get($tab);
    $ogMeta = app(\App\Services\MetaTagsService::class)->forHome($meta);

    $canonicalBase = $tab === \App\Support\HomeFeedTabs::DEFAULT
        ? url('/')
        : url($meta['path']);
    $ranking = app(\App\Services\RankingService::class);
    $feedTotal = ($meta['kind'] ?? 'posts') === 'comments'
        ? $ranking->allCommentsTotal()
        : $ranking->feedTotal($meta['method']);
@endphp

<x-layouts.app :title="$ogMeta['title']" :description="$ogMeta['description']"
               :canonical="\App\Support\Url::canonicalWithPage('page', $canonicalBase)">
    @push('head')
        <x-meta-og :meta="$ogMeta" />
        <x-pagination-head :total="$feedTotal" :per-page="(int) config('orbita.posts.per_page', 25)" />
    @endpush

    <livewire:home-feed :tab="$tab" />
</x-layouts.app>
