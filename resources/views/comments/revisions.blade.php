<x-layouts.app>
    <x-slot:title>Revisões do comentário - {{ config('orbita.name', 'Órbita') }}</x-slot:title>

    <div class="mx-auto max-w-3xl space-y-5">
        <nav class="text-sm text-gray-500 dark:text-gray-400">
            <a href="{{ route('posts.show', ['hashid' => $comment->post->hashid, 'slug' => $comment->post->slug]) }}" class="hover:text-primary-600 dark:hover:text-primary-400">
                {{ \Illuminate\Support\Str::limit($comment->post->title, 50) }}
            </a>
            <span aria-hidden="true"> / </span>
            <span class="text-gray-700 dark:text-gray-300">Revisões do comentário</span>
        </nav>

        <div class="rounded-lg border border-green-300 bg-white dark:border-green-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-green-200 bg-green-50 px-4 py-2 dark:border-green-900 dark:bg-green-950">
                <h2 class="font-semibold text-green-800 dark:text-green-300">Versão atual</h2>
                <span class="rounded-full bg-green-600 px-2 py-0.5 text-sm font-semibold text-white">Atual</span>
            </div>
            <div class="space-y-3 p-4">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Criado em <x-date-time :value="$comment->created_at" />
                    @if ($comment->edited_at)
                        &middot; editado em <x-date-time :value="$comment->edited_at" />
                    @endif
                </div>
                <div class="md-content md-content--comment rounded bg-gray-50 p-3 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                    {!! \App\Support\InlineImages::decorate(
                        \App\Support\Markdown::toHtml((string) $comment->content),
                        $comment->media,
                        auth()->user(),
                        \App\Support\InlineImages::COMMENT_MAX,
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
                            <x-date-time :value="$version->created_at" class="text-gray-600 dark:text-gray-300" />
                        </summary>
                        <div class="border-t border-gray-100 p-4 dark:border-gray-800">
                            <div class="md-content md-content--comment rounded bg-gray-50 p-3 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                {!! \App\Support\InlineImages::decorate(
                                    \App\Support\Markdown::toHtml((string) $version->content),
                                    $comment->media,
                                    auth()->user(),
                                    \App\Support\InlineImages::COMMENT_MAX,
                                ) !!}
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        @else
            <p class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                Este comentário ainda não foi editado. Não há versões anteriores.
            </p>
        @endif
    </div>
</x-layouts.app>
