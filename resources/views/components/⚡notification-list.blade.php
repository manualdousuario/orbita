<?php

use App\Livewire\Concerns\InteractsWithInfiniteScroll;
use App\Models\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;

new class extends Component
{
    use InteractsWithInfiniteScroll;

    #[Locked]
    public string $filter = 'all';

    public function mount(string $filter = 'all'): void
    {
        $this->filter = $filter === 'unread' ? 'unread' : 'all';
    }

    protected function rowsIsland(string $pageName = 'page'): string
    {
        return 'notification-rows';
    }

    protected function navIsland(string $pageName = 'page'): string
    {
        return 'notification-nav';
    }

    #[Renderless]
    public function markRead(int $id): void
    {
        Notification::query()
            ->where('id', $id)
            ->where('user_id', (int) Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => Carbon::now()]);

        $this->dispatch('notifications-changed');
    }

    #[Computed]
    public function notifications()
    {
        return Notification::query()
            ->where('user_id', (int) Auth::id())
            ->when($this->filter === 'unread', fn ($q) => $q->where('is_read', false))
            ->orderByDesc('created_at')
            ->paginate((int) config('orbita.pagination.notifications_per_page'))
            ->withPath(\App\Support\Url::paginationPath())
            ->appends($this->filter === 'unread' ? ['filter' => 'unread'] : []);
    }
}; ?>

<div x-data="infiniteUrl">
    <ul class="space-y-2">
        @island('notification-rows')
            <li style="height:0;margin:0" aria-hidden="true"
                data-page-marker="{{ $this->notifications->currentPage() }}"
                data-page-param="page"
                data-prev-url="{{ \App\Support\Url::canonicalPage($this->notifications->previousPageUrl()) }}"
                data-next-url="{{ $this->notifications->nextPageUrl() }}"></li>

            @forelse ($this->notifications as $notification)
                @php
                    [$iconName, $iconColor] = match ($notification->type) {
                        'comment' => ['chat-bubble-left-right', 'text-primary-600 dark:text-primary-400'],
                        'reply' => ['arrow-uturn-left', 'text-green-600 dark:text-green-400'],
                        'mention' => ['at-symbol', 'text-amber-600 dark:text-amber-400'],
                        'reaction' => ['heart', 'text-pink-600 dark:text-pink-400'],
                        default => ['bell', 'text-gray-500 dark:text-gray-400'],
                    };
                @endphp
                <li wire:key="notif-{{ $notification->id }}"
                    x-data="{ read: {{ $notification->is_read ? 'true' : 'false' }} }">
                    <div class="flex items-start gap-3 rounded-lg border p-3"
                         @class([
                             'border-primary-200 bg-primary-50/60 dark:border-primary-800 dark:bg-primary-950/30' => ! $notification->is_read,
                             'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900' => $notification->is_read,
                         ])
                         :class="read
                             ? 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900'
                             : 'border-primary-200 bg-primary-50/60 dark:border-primary-800 dark:bg-primary-950/30'">
                        <x-dynamic-component :component="'heroicon-o-'.$iconName" class="mt-0.5 h-5 w-5 shrink-0 {{ $iconColor }}" aria-hidden="true" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $notification->title }}
                                    <span x-show="!read" class="ml-1 rounded bg-primary-600 px-1.5 py-0.5 text-sm font-semibold text-white">Nova</span>
                                </p>
                                <x-date-time :value="$notification->created_at" class="shrink-0 text-sm text-gray-500 dark:text-gray-400" />
                            </div>
                            @if ($notification->message)
                                <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">{{ $notification->message }}</p>
                            @endif
                            <div class="mt-1 flex items-center gap-3 text-sm">
                                @if ($notification->link)
                                    <a href="{{ $notification->link }}"
                                       x-on:click="if (read || $event.metaKey || $event.ctrlKey || $event.shiftKey || $event.altKey) return;
                                                   $event.preventDefault(); read = true;
                                                   $wire.markRead({{ $notification->id }}).finally(() => window.location.href = $el.href)"
                                       class="font-medium text-primary-600 hover:underline dark:text-primary-400">Abrir</a>
                                @endif
                                <form x-show="!read" action="{{ route('notifications.read', ['id' => $notification->id]) }}" method="POST"
                                      x-on:submit.prevent="read = true; $wire.markRead({{ $notification->id }})">
                                    @csrf
                                    <button type="submit" class="text-gray-500 hover:underline dark:text-gray-400">Marcar como lida</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </li>
            @empty
                @if ($this->notifications->currentPage() === 1)
                    <li class="rounded-lg border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                        {{ $filter === 'unread' ? 'Nenhuma notificação não lida.' : 'Você não tem notificações.' }}
                    </li>
                @endif
            @endforelse
        @endisland
    </ul>

    @island('notification-nav')
        <x-infinite-pagination :paginator="$this->notifications" page-name="page"
                               island="notification-rows" noun="notificações" />
    @endisland
</div>
