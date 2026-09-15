<?php

use App\Livewire\Concerns\InteractsWithInfiniteScroll;
use App\Services\BookmarkService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    use InteractsWithInfiniteScroll;

    protected function rowsIsland(string $pageName = 'page'): string
    {
        return 'bookmarked-rows';
    }

    protected function navIsland(string $pageName = 'page'): string
    {
        return 'bookmarked-nav';
    }

    #[Computed]
    public function posts()
    {
        return app(BookmarkService::class)
            ->listFor((int) Auth::id(), (int) config('orbita.pagination.bookmarks_per_page'))
            ->withPath(\App\Support\Url::paginationPath());
    }
}; ?>

<div x-data="infiniteUrl">
    <ul class="divide-y divide-gray-200 dark:divide-gray-800">
        @island('bookmarked-rows')
            <li style="height:0;border-width:0" aria-hidden="true"
                data-page-marker="{{ $this->posts->currentPage() }}"
                data-page-param="page"
                data-prev-url="{{ \App\Support\Url::canonicalPage($this->posts->previousPageUrl()) }}"
                data-next-url="{{ $this->posts->nextPageUrl() }}"></li>

            @forelse ($this->posts as $post)
                @php
                    $domain = $post->url ? \App\Support\Url::domain($post->url) : null;
                    $author = $post->user?->authorName();
                    $score = (int) $post->score;
                    $comments = (int) $post->comment_count;
                    $showScore = (bool) config('orbita.posts.show_score', true);
                    $locked = ! $post->allow_comments;
                @endphp
                <li wire:key="bookmarked-post-{{ $post->id }}" @class(['py-4', 'grid grid-cols-[auto_1fr] gap-x-3' => $showScore])>
                    @if ($showScore)
                    <div class="pt-0.5">
                        <span @class([
                            'inline-flex min-w-10 justify-center rounded-md px-1.5 py-1 font-mono text-sm font-semibold tabular-nums',
                            'bg-positive/10 text-positive' => $score > 0,
                            'bg-red-500/10 text-red-600 dark:text-red-400' => $score < 0,
                            'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => $score === 0,
                        ])>{{ $score > 0 ? '+'.$score : $score }}</span>
                    </div>
                    @endif

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <h2 class="min-w-0 text-base font-semibold leading-snug text-balance">
                                <x-post-locked-badge :locked="$locked" />
                                <a href="{{ route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]) }}" wire:navigate
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

                        <p class="mt-1 font-mono text-sm text-gray-500 dark:text-gray-400">
                            {{ $author }}
                            @if ($post->user?->isLinkable())
                                &middot; &#64;{{ $post->user->username }}
                            @endif
                            &middot; <x-date-time :value="$post->created_at" />
                        </p>

                        <div class="mt-1 flex items-center gap-1">
                            <a href="{{ route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]) }}#comentarios" wire:navigate
                                class="inline-flex items-center gap-1.5 rounded-md px-2 py-3 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100"
                                aria-label="{{ $comments }} {{ $comments === 1 ? 'comentário' : 'comentários' }}">
                                <x-heroicon-o-chat-bubble-left class="h-4 w-4" aria-hidden="true" />
                                <span class="font-mono tabular-nums">{{ $comments }}</span>
                            </a>

                            <x-bookmark-button :hashid="$post->hashid" :bookmarked="true" :compact="true" />
                        </div>
                    </div>
                </li>
            @empty
                @if ($this->posts->currentPage() === 1)
                    <li class="rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center dark:border-gray-700">
                        <x-heroicon-o-bookmark class="mx-auto h-8 w-8 text-gray-500" aria-hidden="true" />
                        <p class="mt-3 text-gray-600 dark:text-gray-300">Você ainda não acompanha nenhum post.</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Toque em <span class="font-medium">Acompanhar</span> em qualquer post para ver aqui os novos comentários.
                        </p>
                    </li>
                @endif
            @endforelse
        @endisland
    </ul>

    @island('bookmarked-nav')
        <x-infinite-pagination :paginator="$this->posts" page-name="page" island="bookmarked-rows" noun="posts" />
    @endisland
</div>
