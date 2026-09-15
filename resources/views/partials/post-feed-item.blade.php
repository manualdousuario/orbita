@php
    $id = (int) $post->id;
    $domain = \App\Support\Url::domain($post->url ?? null);
    $author = $post->display_name ?: $post->username;
    $when = $post->published_at ?? $post->created_at ?? null;
    $count = (int) ($post->comment_count ?? 0);
    $summary = ($meta['summaries'][$id] ?? ['reactions' => [], 'score' => 0, 'count' => 0])
        + ['user_reaction' => $meta['mine'][$id] ?? null];
    $score = (int) $summary['score'];
    $image = $meta['images'][$id] ?? null;
    $permalink = route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug ?? null]);
    $pinned = ($showsPinned ?? false) && (bool) ($post->is_pinned ?? false);
    $locked = ! ($post->allow_comments ?? true);
    $showScore = (bool) config('orbita.posts.show_score', true);
@endphp
<li wire:key="post-{{ $id }}" class="flex items-start gap-x-3 pt-4 pb-2">
    @if ($showScore)
    <div class="pt-0.5">
        <span x-data="{ score: {{ $score }} }"
              x-on:reaction-score-updated.window="if ($event.detail.type === 'post' && $event.detail.id === {{ $id }}) score = $event.detail.score"
              x-text="score > 0 ? '+' + score : score"
              :aria-label="`${score} ${Math.abs(score) === 1 ? 'ponto' : 'pontos'}`"
              aria-label="{{ $score }} {{ abs($score) === 1 ? 'ponto' : 'pontos' }}"
              @class([
                  'inline-flex min-w-10 justify-center rounded-md px-1.5 py-1 font-mono text-sm font-semibold tabular-nums',
                  'bg-positive/10 text-positive' => $score > 0,
                  'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => $score === 0,
                  'bg-red-500/10 text-red-600 dark:text-red-400' => $score < 0,
              ])
              :class="{
                  'bg-positive/10 text-positive': score > 0,
                  'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400': score === 0,
                  'bg-red-500/10 text-red-600 dark:text-red-400': score < 0,
              }">
            {{ $score > 0 ? '+'.$score : $score }}
        </span>
    </div>
    @endif

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-baseline gap-x-2">
            <h2 class="min-w-0 text-base font-semibold leading-snug text-balance sm:text-lg">
                @if ($pinned)
                    <span class="mr-1 inline-flex h-[1lh] items-center align-top text-primary-600 dark:text-primary-400" title="Post fixado">
                        <x-icon-pin class="h-4 w-4" aria-hidden="true" />
                        <span class="sr-only">Post fixado</span>
                    </span>
                @endif
                <x-post-locked-badge :locked="$locked" />
                <a href="{{ $permalink }}" wire:navigate
                   class="break-words text-gray-900 visited:text-visited hover:text-primary-600 dark:text-gray-100 dark:hover:text-primary-400">
                    {{ $post->title }}
                </a>
            </h2>

            @if ($domain)
                <a href="{{ $post->url }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex shrink-0 items-center gap-1 font-mono text-sm text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
                    <x-heroicon-o-link class="h-3.5 w-3.5" aria-hidden="true" />
                    {{ $domain }}
                </a>
            @endif
        </div>

        <p class="mt-1 flex flex-wrap items-center gap-x-1.5 font-mono text-sm text-gray-500 dark:text-gray-400">
            <span>{{ $author }}</span>
            @if ($post->username && ! ($post->anonymized_at ?? null))
                <span aria-hidden="true">&middot;</span>
                <span>&#64;{{ $post->username }}</span>
            @endif
            @if ($when)
                <span aria-hidden="true">&middot;</span>
                <x-date-time :value="$when" />
            @endif
        </p>

        <div class="mt-1 flex flex-wrap items-center gap-x-4 font-mono">
            <livewire:reactions type="post" :id="$id" :compact="false"
                                :summary="$summary" :reported="isset($meta['reported'][$id])" :key="'react-post-'.$id" />

            <a href="{{ $permalink }}#comentarios" wire:navigate
               aria-label="{{ $count }} {{ $count === 1 ? 'comentário' : 'comentários' }}"
                class="inline-flex items-center gap-1.5 py-3 text-sm text-gray-500 transition-colors hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
                <x-heroicon-o-chat-bubble-left class="h-4 w-4" aria-hidden="true" />
                <span class="font-mono tabular-nums" aria-hidden="true">{{ $count }}</span>
                <span aria-hidden="true">{{ $count === 1 ? 'comentário' : 'comentários' }}</span>
            </a>

            <x-bookmark-button :hashid="$post->hashid" :bookmarked="isset($meta['bookmarked'][$id])" :compact="false" />

            <x-share-button :url="$permalink" :title="$post->title" :compact="false" />

            @if ($canAdmin)
                <a href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $id]) }}"
                       aria-label="Editar no admin" title="Editar no admin"
                        class="inline-flex items-center gap-1 py-3.5 text-sm text-gray-500 transition-colors hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
                    <x-heroicon-o-wrench-screwdriver class="h-4 w-4" aria-hidden="true" />
                </a>
            @endif
        </div>
    </div>

    @if ($image)
        <a href="{{ $permalink }}" wire:navigate tabindex="-1" aria-hidden="true" class="shrink-0">
            <x-media-image :media="$image" :width="160" :height="160" fit="crop" alt=""
                           class="h-14 w-14 rounded-md border border-gray-200 object-cover sm:h-16 sm:w-16 dark:border-gray-800" />
        </a>
    @endif
</li>
