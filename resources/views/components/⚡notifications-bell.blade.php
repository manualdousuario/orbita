<?php

use App\Models\Notification;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public int $unreadCount = 0;

    /** @var array<int, array<string, mixed>> */
    public array $recent = [];

    public function mount(): void
    {
        $this->unreadCount = auth()->user()?->unreadNotificationsCount() ?? 0;
    }

    #[On('notifications-changed')]
    public function refresh(): void
    {
        $this->unreadCount = Notification::query()
            ->where('user_id', (int) auth()->id())
            ->where('is_read', false)
            ->count();
    }

    public function markRead(int $id): void
    {
        Notification::query()
            ->where('id', $id)
            ->where('user_id', (int) auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => Carbon::now()]);

        foreach ($this->recent as $i => $n) {
            if ((int) $n['id'] === $id) {
                $this->recent[$i]['is_read'] = true;
            }
        }

        $this->refresh();
        $this->dispatch('notifications-changed');
    }

    public function markAllRead(): void
    {
        Notification::query()
            ->where('user_id', (int) auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => Carbon::now()]);

        $this->refresh();
        $this->loadRecent();
    }

    public function loadRecent(): void
    {
        $this->recent = Notification::query()
            ->where('user_id', (int) auth()->id())
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (Notification $n) => [
                'id' => (int) $n->id,
                'type' => (string) $n->type,
                'title' => (string) $n->title,
                'message' => $n->message ? \Illuminate\Support\Str::limit((string) $n->message, 70) : null,
                'link' => $n->link,
                'is_read' => (bool) $n->is_read,
                'when' => $n->created_at ? Carbon::parse($n->created_at)->locale('pt_BR')->diffForHumans() : '',
            ])
            ->all();
    }
}; ?>

<div class="relative" x-data="{ open: false }" @keydown.escape="open = false; $refs.trigger.focus()" wire:poll.60s="refresh">
    <button type="button" x-ref="trigger" @click="open = !open; if (open) $wire.loadRecent()"
            aria-label="Notificações{{ $unreadCount > 0 ? ' — '.$unreadCount.' não '.($unreadCount === 1 ? 'lida' : 'lidas') : '' }}"
            class="relative flex h-11 w-11 items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">
        <x-heroicon-o-bell class="h-5 w-5" aria-hidden="true" />
        @if ($unreadCount > 0)
            <span aria-hidden="true"
                  class="absolute -right-0.5 -top-0.5 inline-flex min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-sm font-semibold leading-4 text-white">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <p role="status" aria-live="polite" class="sr-only">
        @if ($unreadCount > 0)
            {{ $unreadCount }} {{ $unreadCount === 1 ? 'notificação não lida' : 'notificações não lidas' }}
        @endif
    </p>

    <div x-show="open" x-transition @click.outside="open = false" x-cloak
         class="dropdown-sheet z-30 overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2 dark:border-gray-700">
            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">Notificações</span>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllRead"
                        class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400 data-loading:opacity-50">
                    Ler todas
                </button>
            @endif
        </div>

        <div class="max-h-[min(24rem,55vh)] overflow-y-auto">
            @forelse ($recent as $n)
                <div wire:key="bell-notif-{{ $n['id'] }}"
                     @class([
                         'flex items-start gap-1 border-b border-gray-100 pr-2 dark:border-gray-700',
                         'bg-primary-50/60 dark:bg-primary-950/30' => ! $n['is_read'],
                     ])>
                    <a href="{{ $n['link'] ?? route('notifications.index') }}"
                       x-on:click="if ($event.metaKey || $event.ctrlKey || $event.shiftKey || $event.altKey) return;
                                   $event.preventDefault();
                                   $wire.markRead({{ $n['id'] }}).finally(() => window.location.href = $el.href)"
                       class="block min-w-0 flex-1 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $n['title'] }}</p>
                        @if ($n['message'])
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $n['message'] }}</p>
                        @endif
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $n['when'] }}</p>
                    </a>

                    @if (! $n['is_read'])
                        <button type="button" wire:click="markRead({{ $n['id'] }})"
                                title="Marcar como lida"
                                aria-label="Marcar como lida: {{ $n['title'] }}"
                                class="group mt-2.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full hover:bg-primary-100 dark:hover:bg-primary-900/50 data-loading:opacity-50">
                            <span class="h-2 w-2 rounded-full bg-primary-500 transition-transform group-hover:scale-150" aria-hidden="true"></span>
                        </button>
                    @else
                        <span class="h-8 w-8 shrink-0" aria-hidden="true"></span>
                    @endif
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nenhuma notificação.</p>
            @endforelse
        </div>

        <a href="{{ route('notifications.index') }}"
           class="block border-t border-gray-100 px-4 py-2 text-center text-sm font-medium text-primary-600 hover:bg-gray-50 dark:border-gray-700 dark:text-primary-400 dark:hover:bg-gray-700/50">
            Ver todas
        </a>
    </div>
</div>
