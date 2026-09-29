@php
    $displayName = $user->display_name ?: $user->username;
    $canManage = auth()->user()?->can('update', $user) ?? false;
@endphp

<x-layouts.app :title="$meta['title']" :description="$meta['description']"
               :canonical="\App\Support\Url::canonicalWithPage(['posts', 'comments'])">
    @push('head')
        <x-meta-og :meta="$meta" />
        <x-pagination-head :total="$tab === 'comments' ? $commentsTotal : $postsTotal"
                           :per-page="(int) config('orbita.pagination.profile_per_page')"
                           :page-name="$tab" />
    @endpush

    <div class="space-y-6">
        <div class="flex flex-col items-center gap-4 rounded-lg border border-gray-200 bg-white p-5 sm:flex-row sm:items-start dark:border-gray-800 dark:bg-gray-900">
            <x-avatar :user="$user" class="h-20 w-20 shrink-0" :alt="$displayName" />

            <div class="flex-1 text-center sm:text-left">
                <div class="flex items-center justify-center gap-1.5 sm:justify-start">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $displayName }}</h1>
                    @if ($user->website)
                        <a href="{{ $user->website }}" target="_blank" rel="nofollow noopener ugc"
                           title="Site de {{ $displayName }}" aria-label="Site de {{ $displayName }}"
                           class="-m-1 inline-flex min-h-6 min-w-6 items-center justify-center p-1 text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
                            <x-heroicon-o-link class="h-4 w-4" aria-hidden="true" />
                        </a>
                    @endif
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">&#64;{{ $user->username }}</p>
                @if ($user->bio)
                    <p class="mt-2 text-gray-700 dark:text-gray-300">{{ $user->bio }}</p>
                @endif
                @if ($joinedAt)
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Membro desde <time datetime="{{ $joinedAt->toDateString() }}">{{ $joinedAt->isoFormat('LL') }}</time>
                    </p>
                @endif
            </div>

            @if ($canManage)
                <div class="flex shrink-0 flex-col gap-2">
                    <a href="{{ route('users.edit', ['username' => $user->username]) }}" wire:navigate
                       class="rounded-md border border-gray-300 px-3 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                        Editar perfil
                    </a>
                </div>
            @endif
        </div>

        @php
            $profileTabs = [
                'posts' => ['label' => 'Posts', 'count' => $postsTotal, 'url' => route('users.profile', ['username' => $user->username])],
                'comments' => ['label' => 'Comentários', 'count' => $commentsTotal, 'url' => route('users.profile.comments', ['username' => $user->username])],
            ];
        @endphp

        <section>
            <nav class="flex gap-6 border-b border-gray-200 dark:border-gray-800" aria-label="Conteúdo de {{ $displayName }}">
                @foreach ($profileTabs as $key => $profileTab)
                    <a href="{{ $profileTab['url'] }}" wire:navigate
                       @if ($tab === $key) aria-current="page" @endif
                       @class([
                           '-mb-px inline-flex items-baseline gap-2 border-b-2 py-3 text-base font-semibold transition',
                           'border-primary-600 text-gray-900 dark:border-primary-400 dark:text-gray-100' => $tab === $key,
                           'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-100' => $tab !== $key,
                       ])>
                        {{ $profileTab['label'] }}
                        <span class="font-mono text-sm font-normal tabular-nums text-gray-500 dark:text-gray-400">{{ $profileTab['count'] }}</span>
                    </a>
                @endforeach
            </nav>

            @if ($tab === 'comments')
                <livewire:profile-comments :user-id="$user->id" />
            @else
                <livewire:profile-posts :user-id="$user->id" />
            @endif
        </section>
    </div>
</x-layouts.app>
