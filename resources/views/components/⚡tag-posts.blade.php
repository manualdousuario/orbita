<?php

use App\Livewire\Concerns\InteractsWithInfiniteScroll;
use App\Models\Post;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    use InteractsWithInfiniteScroll;

    #[Locked]
    public int $tagId = 0;

    public function mount(int $tagId): void
    {
        $this->tagId = $tagId;
    }

    protected function rowsIsland(string $pageName = 'page'): string
    {
        return 'tag-rows';
    }

    protected function navIsland(string $pageName = 'page'): string
    {
        return 'tag-nav';
    }

    #[Computed]
    public function posts()
    {
        return Post::query()
            ->with('user')
            ->whereIn('status', ['published', 'closed'])
            ->whereNull('version_of')
            ->whereHas('terms', fn ($query) => $query->where('terms.id', $this->tagId))
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(max(1, (int) config('orbita.posts.per_page', 25)))
            ->withPath(\App\Support\Url::paginationPath());
    }
}; ?>

<div x-data="infiniteUrl">
    <ul class="divide-y divide-gray-200 dark:divide-gray-800">
        @island('tag-rows')
            <li style="height:0;border-width:0" aria-hidden="true"
                data-page-marker="{{ $this->posts->currentPage() }}"
                data-page-param="page"
                data-prev-url="{{ \App\Support\Url::canonicalPage($this->posts->previousPageUrl()) }}"
                data-next-url="{{ $this->posts->nextPageUrl() }}"></li>

            @forelse ($this->posts as $post)
                @php
                    $domain = \App\Support\Url::domain($post->url);
                    $author = $post->user?->authorName() ?? 'Anônimo';
                    $when = $post->published_at ?? $post->created_at;
                    $count = (int) $post->comment_count;
                    $score = (int) $post->score;
                    $permalink = route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]);
                    $showScore = (bool) config('orbita.posts.show_score', true);
                    $locked = ! $post->allow_comments;
                @endphp
                <li wire:key="tag-post-{{ $post->id }}" @class(['py-4', 'grid grid-cols-[auto_1fr] gap-x-3' => $showScore])>
                    @if ($showScore)
                    <div class="pt-0.5">
                        <span aria-label="{{ $score }} {{ $score === 1 ? 'ponto' : 'pontos' }}"
                              @class([
                                  'inline-flex min-w-10 justify-center rounded-md px-1.5 py-1 font-mono text-sm font-semibold tabular-nums',
                                  'bg-positive/10 text-positive' => $score > 0,
                                  'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => $score === 0,
                              ])>
                            {{ $score > 0 ? '+'.$score : $score }}
                        </span>
                    </div>
                    @endif

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <h2 class="min-w-0 text-base font-semibold leading-snug text-balance sm:text-lg">
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
                            @if ($post->user?->isLinkable())
                                <span aria-hidden="true">&middot;</span>
                                <span>&#64;{{ $post->user->username }}</span>
                            @endif
                            @if ($when)
                                <span aria-hidden="true">&middot;</span>
                                <x-date-time :value="$when" />
                            @endif
                            <span aria-hidden="true">&middot;</span>
                            <a href="{{ $permalink }}#comentarios" wire:navigate class="inline-flex items-center gap-1 hover:text-primary-600 dark:hover:text-primary-400">
                                <x-heroicon-o-chat-bubble-left class="h-3.5 w-3.5" aria-hidden="true" />
                                <span class="tabular-nums">{{ $count }}</span>
                            </a>
                        </p>
                    </div>
                </li>
            @empty
                @if ($this->posts->currentPage() === 1)
                    <li class="py-8 text-center text-gray-500 dark:text-gray-400">Ainda não há posts com esta tag.</li>
                @endif
            @endforelse
        @endisland
    </ul>

    @island('tag-nav')
        <x-infinite-pagination :paginator="$this->posts" page-name="page" island="tag-rows" noun="posts" />
    @endisland
</div>
