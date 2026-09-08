@php
    $replyCount = (int) ($node['descendant_count'] ?? 0);
    $hasReplies = ! empty($node['replies']);
    $canModerate = $node['can_edit'] || $node['can_delete'] || ($node['can_admin'] ?? false);
    $repliesId = 'replies-'.$node['id'];
    $isRoot = $isRoot ?? false;
    $depth = (int) ($depth ?? 0);
    $railPad = fn (int $level) => $level <= 2 ? 21 : 0;
    $childPad = $railPad($depth + 1);
    $replyPad = 0;
    for ($level = 1; $level <= $depth; $level++) {
        $replyPad += $railPad($level) + 1;
    }
@endphp

<li wire:key="comment-{{ $node['id'] }}" id="comment-{{ $node['hashid'] }}"
    @class(['scroll-mt-24', 'py-5 first:pt-0 last:pb-0' => $isRoot])>
    <div x-data="{ replying: false, collapsed: false }"
         x-init="$watch('replying', (open) => $dispatch('reply-toggled', { open }))"
         @if ($depth > 0) style="--reply-pad: {{ $replyPad }}px; --reply-gaps: {{ $depth }}" @endif
         x-on:cancel-reply="replying = false"
         x-on:reply-posted.window="if ($event.detail.parentId === {{ $node['id'] }}) replying = false">

        <div class="flex gap-x-2.5">
            <div class="hidden shrink-0 sm:block">
                @if ($node['user'])
                    @if ($node['username'])
                        <a href="{{ route('users.profile', ['username' => $node['username']]) }}" wire:navigate tabindex="-1" aria-hidden="true">
                            <x-avatar :user="$node['user']" class="h-[42px] w-[42px]" />
                        </a>
                    @else
                        <x-avatar :user="$node['user']" class="h-[42px] w-[42px]" />
                    @endif
                @else
                    <span class="block h-[42px] w-[42px] rounded-full bg-gray-200 dark:bg-gray-800" aria-hidden="true"></span>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 font-mono text-sm text-gray-500 dark:text-gray-400">
                    @if ($node['user'])
                        @if ($node['username'])
                            <a href="{{ route('users.profile', ['username' => $node['username']]) }}" wire:navigate tabindex="-1" aria-hidden="true" class="sm:hidden">
                                <x-avatar :user="$node['user']" class="h-[30px] w-[30px]" />
                            </a>
                        @else
                            <x-avatar :user="$node['user']" class="h-[30px] w-[30px] sm:hidden" />
                        @endif
                    @else
                        <span class="block h-[30px] w-[30px] rounded-full bg-gray-200 sm:hidden dark:bg-gray-800" aria-hidden="true"></span>
                    @endif

                    @if ($node['username'])
                        <a href="{{ route('users.profile', ['username' => $node['username']]) }}" wire:navigate
                           class="font-sans text-sm font-semibold text-gray-900 hover:text-primary-600 dark:text-gray-100 dark:hover:text-primary-400">
                            {{ $node['author'] }}
                        </a>
                    @else
                        <span class="font-sans text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $node['author'] }}</span>
                    @endif

                    @if ($node['user']?->website)
                        <span aria-hidden="true">&middot;</span>
                        <a href="{{ $node['user']->website }}" target="_blank" rel="nofollow noopener ugc"
                           title="Site de {{ $node['author'] }}"
                           class="hover:text-primary-600 dark:hover:text-primary-400">
                            <x-heroicon-o-link class="h-3.5 w-3.5" aria-hidden="true" />
                        </a>
                    @endif

                    @if ($node['username'])
                        <span aria-hidden="true">&middot;</span>
                        <span>&#64;{{ $node['username'] }}</span>
                    @endif

                    @if ($node['is_op'])
                        <span class="rounded bg-primary-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary-700 dark:bg-primary-950 dark:text-primary-300"
                              title="OP do post">OP</span>
                    @endif

                    <span aria-hidden="true">&middot;</span>
                    <a href="#comment-{{ $node['hashid'] }}"
                       title="{{ $node['created_at']?->locale('pt_BR')->isoFormat('D [de] MMMM [de] YYYY [às] HH:mm') }}"
                       class="hover:text-primary-600 dark:hover:text-primary-400">
                        {{ $node['created_at']?->locale('pt_BR')->diffForHumans() }}
                    </a>

                    @if ($node['edited_at'])
                        <a href="{{ route('comments.revisions', ['hashid' => $node['hashid']]) }}" wire:navigate
                           class="hover:text-primary-600 dark:hover:text-primary-400" title="Editado - ver revisões">
                            (editado)
                        </a>
                    @endif

                    @if ($hasReplies)
                        <button type="button" x-on:click="collapsed = !collapsed"
                                :aria-expanded="collapsed ? 'false' : 'true'"
                                aria-controls="{{ $repliesId }}"
                                :aria-label="collapsed ? 'Expandir esta conversa' : 'Recolher esta conversa'"
                                aria-label="Recolher esta conversa"
                                class="inline-flex h-6 min-w-6 items-center justify-center rounded text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                            <span aria-hidden="true" class="text-sm font-semibold leading-none" x-text="collapsed ? '+' : '−'">−</span>
                        </button>
                        <span x-show="collapsed" x-cloak class="text-gray-500 dark:text-gray-400">
                            {{ $replyCount }} {{ $replyCount === 1 ? 'resposta' : 'respostas' }}
                        </span>
                    @endif
                </div>

                <div x-show="!collapsed" class="mt-1">
                    <div class="md-content md-content--comment max-w-[64ch] text-gray-800 dark:text-gray-200">
                        {!! \App\Support\InlineImages::decorate($node['content_html'], $node['media'] ?? [], auth()->user(), \App\Support\InlineImages::COMMENT_MAX) !!}
                    </div>

                    <div class="mt-0.5 flex flex-wrap items-center gap-x-4 font-mono">
                        <livewire:reactions type="comment" :id="$node['id']" :compact="false"
                                            :summary="$node['reaction_summary']" :reported="$node['reported']" :key="'react-comment-'.$node['id']" />

                        @if ($allowComments && $node['can_reply'])
                            <button type="button" x-on:click="replying = !replying; replying && $nextTick(() => $refs.replyForm.querySelector('textarea')?.focus())"
                                    class="inline-flex items-center gap-1.5 rounded-md py-3 text-sm font-medium text-gray-500 transition-colors hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
                                <x-heroicon-o-arrow-uturn-left class="h-4 w-4" aria-hidden="true" />
                                Responder
                            </button>
                        @endif

                        @if ($canModerate)
                            <div class="relative" x-data="{ menu: false }" @keydown.escape="menu = false; $refs.trigger.focus()">
                                <button type="button" x-ref="trigger" @click="menu = !menu"
                                        aria-label="Mais ações" aria-haspopup="true" :aria-expanded="menu ? 'true' : 'false'"
                                        class="inline-flex items-center justify-center rounded-md p-3.5 text-gray-500 transition-colors hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
                                    <x-heroicon-o-ellipsis-horizontal class="h-4 w-4" aria-hidden="true" />
                                </button>

                                <div x-show="menu" x-transition @click.outside="menu = false" x-cloak
                                     class="absolute left-0 z-20 mt-1 w-40 overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                    @if ($node['can_edit'])
                                        <a href="{{ route('comments.edit', ['hashid' => $node['hashid']]) }}" wire:navigate
                                            class="flex items-center gap-2 px-3 py-3 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                            <x-heroicon-o-pencil-square class="h-4 w-4" aria-hidden="true" />
                                            Editar
                                        </a>
                                    @endif
                                    @if ($node['can_admin'] ?? false)
                                        <a href="{{ \App\Filament\Resources\Comments\CommentResource::getUrl('edit', ['record' => $node['id']]) }}"
                                            class="flex items-center gap-2 px-3 py-3 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                            <x-heroicon-o-wrench-screwdriver class="h-4 w-4" aria-hidden="true" />
                                            Editar no admin
                                        </a>
                                    @endif
                                    @if ($node['can_delete'])
                                        <form method="POST" action="{{ route('comments.destroy', ['hashid' => $node['hashid']]) }}"
                                              onsubmit="return confirm('Deletar este comentário?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="flex w-full items-center gap-2 px-3 py-3 text-left text-sm text-red-600 hover:bg-gray-100 dark:text-red-400 dark:hover:bg-gray-700">
                                                <x-heroicon-o-trash class="h-4 w-4" aria-hidden="true" />
                                                Deletar
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    @if ($allowComments && $node['can_reply'])
                        <div x-show="replying" x-cloak x-transition x-ref="replyForm" class="reply-breakout">
                            <livewire:comment-form :post-id="$postId" :parent-id="$node['id']" :key="'reply-form-'.$node['id']" />
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($hasReplies)
            <div id="{{ $repliesId }}" x-show="!collapsed"
                 class="mt-3 grid grid-cols-[1px_1fr] gap-x-2 sm:gap-x-5 {{ $childPad === 21 ? 'pl-[21px]' : 'pl-0 sm:pl-[21px]' }}">
                <button type="button" x-on:click="collapsed = true" tabindex="-1" aria-hidden="true"
                        class="relative cursor-pointer bg-gray-200 transition-colors hover:bg-primary-500 dark:bg-gray-700 dark:hover:bg-primary-500">
                    <span class="absolute inset-y-0 -left-2 -right-2" aria-hidden="true"></span>
                </button>

                <ul class="min-w-0 space-y-4">
                    @foreach ($node['replies'] as $reply)
                        @include('partials.comment-node', [
                            'node' => $reply,
                            'postId' => $postId,
                            'allowComments' => $allowComments,
                            'isRoot' => false,
                            'depth' => $depth + 1,
                        ])
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</li>
