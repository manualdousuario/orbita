@php
    $author = $comment->display_name ?: $comment->username;
    $isLinkable = $comment->username && ! ($comment->anonymized_at ?? null);
    $permalink = route('posts.show', ['hashid' => $comment->post_hashid, 'slug' => $comment->post_slug ?? null]);
    $commentLink = $permalink.'#comment-'.$comment->hashid;
@endphp

<li wire:key="comment-feed-{{ $comment->id }}" class="flex items-start gap-x-2.5 pt-4 pb-2">
    <div class="hidden shrink-0 sm:block">
        <x-avatar :user="$comment" class="h-[42px] w-[42px]" />
    </div>

    <div class="min-w-0 flex-1">
        <p class="flex flex-wrap items-center gap-x-1.5 font-mono text-sm text-gray-500 dark:text-gray-400">
            @if ($isLinkable)
                <a href="{{ route('users.profile', ['username' => $comment->username]) }}" wire:navigate
                   class="font-sans text-sm font-semibold text-gray-900 hover:text-primary-600 dark:text-gray-100 dark:hover:text-primary-400">
                    {{ $author }}
                </a>
            @else
                <span class="font-sans text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $author }}</span>
            @endif
            <span aria-hidden="true">&middot;</span>
            <a href="{{ $commentLink }}" wire:navigate
               class="hover:text-primary-600 dark:hover:text-primary-400">
                <x-date-time :value="$comment->created_at" />
            </a>
        </p>

        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
            comentou em
            <a href="{{ $permalink }}" wire:navigate class="font-medium text-gray-700 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400">
                {{ $comment->post_title }}
            </a>
        </p>

        <div class="md-content md-content--comment mt-1.5 max-w-[64ch] text-gray-800 dark:text-gray-200">
            {!! \App\Support\InlineImages::decorate(
                \App\Support\Markdown::toHtml((string) $comment->content),
                null,
                auth()->user(),
                \App\Support\InlineImages::COMMENT_MAX,
            ) !!}
        </div>
    </div>
</li>
