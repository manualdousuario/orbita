@php
    $meta = app(\App\Services\MetaTagsService::class)->forGenericPage(
        'Buscar',
        'Busque posts e comentários por palavra-chave no '.config('orbita.name', 'Órbita').'.',
        false,
    );
@endphp

<x-layouts.app :title="$meta['title']" :description="$meta['description']">
    @push('head')
        <x-meta-og :meta="$meta" />
    @endpush

    <livewire:search />
</x-layouts.app>
