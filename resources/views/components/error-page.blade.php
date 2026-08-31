@props(['code', 'title', 'message', 'accent' => 'primary'])

@php
    $accentClass = match ($accent) {
        'danger' => 'text-red-500',
        'warning' => 'text-amber-500',
        default => 'text-primary-600 dark:text-primary-400',
    };
    $meta = app(\App\Services\MetaTagsService::class)->forGenericPage($code.' - '.$title, $message, false);
@endphp

<x-layouts.app :title="$meta['title']" :description="$meta['description']" :fab="false">
    @push('head')
        <x-meta-og :meta="$meta" />
    @endpush

    <div class="mx-auto max-w-lg py-12 text-center">
        <p class="text-6xl font-extrabold {{ $accentClass }}">{{ $code }}</p>
        <h1 class="mt-4 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $title }}</h1>
        <p class="mt-3 text-gray-600 dark:text-gray-400">{{ $message }}</p>
        <a href="{{ url('/') }}" wire:navigate class="mt-8 inline-flex items-center gap-2 rounded-md bg-primary-600 px-5 py-3 text-sm font-semibold text-white hover:bg-primary-700">
            <x-heroicon-o-home class="h-4 w-4" aria-hidden="true" />
            Voltar ao início
        </a>
    </div>
</x-layouts.app>
