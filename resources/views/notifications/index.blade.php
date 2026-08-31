<x-layouts.app :title="'Notificações - '.config('orbita.name', 'Órbita')">
    @push('head')
        <meta name="robots" content="noindex, nofollow">
    @endpush

    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Notificações</h1>
        @if ($unreadCount > 0)
            <form action="{{ route('notifications.read-all') }}" method="POST">
                @csrf
                <button type="submit"
                        class="rounded-md bg-primary-600 px-3 py-3 text-sm font-semibold text-white hover:bg-primary-700">
                    Ler todas
                </button>
            </form>
        @endif
    </div>

    @if (session('success'))
        <div class="rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-700 dark:bg-green-950/40 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif

    <div class="border-b border-gray-200 dark:border-gray-800">
        <nav class="-mb-px flex gap-4 text-sm font-medium">
            <a href="{{ route('notifications.index', ['filter' => 'all']) }}" wire:navigate
                @class([
                    'border-b-2 px-1 py-2',
                    'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' => $filter === 'all',
                    'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $filter !== 'all',
                ])>Todas</a>
            <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" wire:navigate
                @class([
                    'border-b-2 px-1 py-2',
                    'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' => $filter === 'unread',
                    'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $filter !== 'unread',
                ])>
                Não lidas
                @if ($unreadCount > 0)
                    <span x-data="{ n: {{ $unreadCount }} }" x-on:notifications-changed.window="n--"
                          x-show="n > 0"
                          class="ml-1 rounded-full bg-red-600 px-1.5 text-sm font-semibold text-white"
                          x-text="n">{{ $unreadCount }}</span>
                @endif
            </a>
        </nav>
    </div>

    <livewire:notification-list :filter="$filter" />
</x-layouts.app>
