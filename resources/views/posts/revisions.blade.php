@php
    $postUrl = route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]);
    $revisionsMeta = app(\App\Services\MetaTagsService::class)->forRevisions(
        (string) $post->title,
        $postUrl,
        route('posts.revisions', ['hashid' => $post->hashid]),
    );
@endphp

<x-layouts.app>
    <x-slot:title>Revisões - {{ $post->title }}</x-slot:title>

    @push('head')
        <x-meta-og :meta="$revisionsMeta" />
    @endpush

    <div class="mx-auto max-w-3xl space-y-5">
        <nav class="text-sm text-gray-500 dark:text-gray-400">
            <a href="{{ route('home') }}" class="hover:text-primary-600 dark:hover:text-primary-400">Início</a>
            <span aria-hidden="true"> / </span>
            <a href="{{ route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]) }}" class="hover:text-primary-600 dark:hover:text-primary-400">
                {{ \Illuminate\Support\Str::limit($post->title, 50) }}
            </a>
            <span aria-hidden="true"> / </span>
            <span class="text-gray-700 dark:text-gray-300">Revisões</span>
        </nav>

        <div class="rounded-lg border border-green-300 bg-white dark:border-green-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-green-200 bg-green-50 px-4 py-2 dark:border-green-900 dark:bg-green-950">
                <h2 class="font-semibold text-green-800 dark:text-green-300">Versão atual</h2>
                <span class="rounded-full bg-green-600 px-2 py-0.5 text-sm font-semibold text-white">Atual</span>
            </div>
            <div class="space-y-3 p-4">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Criado em {{ $post->created_at?->format('d/m/Y H:i') }}
                    @if ($post->edited_at)
                        &middot; editado em {{ $post->edited_at->format('d/m/Y H:i') }}
                    @endif
                </div>
                <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $post->title }}</h3>
                @if ($post->url)
                    <p class="break-all text-sm"><span class="font-medium">URL:</span>
                        <a href="{{ $post->url }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:underline dark:text-primary-400">{{ $post->url }}</a>
                    </p>
                @endif
                <div class="md-content md-content--post rounded bg-gray-50 p-3 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                    {!! \App\Support\InlineImages::decorate(
                        \App\Support\Markdown::toHtml((string) $post->content),
                        $post->media,
                        auth()->user(),
                        \App\Support\InlineImages::POST_MAX,
                    ) !!}
                </div>
            </div>
        </div>

        @if ($versions->isNotEmpty())
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Versões anteriores ({{ $versions->count() }})
            </h2>
            <div class="space-y-3">
                @foreach ($versions->reverse() as $index => $version)
                    <details class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900" {{ $loop->first ? 'open' : '' }}>
                        <summary class="flex cursor-pointer items-center gap-2 px-4 py-2 text-sm">
                            <span class="rounded bg-gray-200 px-2 py-0.5 text-sm font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                                v{{ $versions->count() - $index }}
                            </span>
                            <span class="text-gray-600 dark:text-gray-300">{{ $version->created_at?->format('d/m/Y H:i') }}</span>
                        </summary>
                        <div class="space-y-3 border-t border-gray-100 p-4 dark:border-gray-800">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $version->title }}</h3>
                            @if ($version->url)
                                <p class="break-all text-sm"><span class="font-medium">URL:</span>
                                    <a href="{{ $version->url }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:underline dark:text-primary-400">{{ $version->url }}</a>
                                </p>
                            @endif
                            <div class="md-content md-content--post rounded bg-gray-50 p-3 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                {!! \App\Support\InlineImages::decorate(
                                    \App\Support\Markdown::toHtml((string) $version->content),
                                    $post->media,
                                    auth()->user(),
                                    \App\Support\InlineImages::POST_MAX,
                                ) !!}
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        @else
            <p class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                Este post ainda não foi editado. Não há versões anteriores.
            </p>
        @endif

        <a href="{{ route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]) }}"
           class="inline-flex items-center gap-1 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
            <x-heroicon-o-arrow-left class="h-4 w-4" aria-hidden="true" />
            Voltar ao post
        </a>
    </div>
</x-layouts.app>
