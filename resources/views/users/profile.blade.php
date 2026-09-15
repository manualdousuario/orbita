@php
    $displayName = $user->display_name ?: $user->username;
    $canManage = auth()->user()?->can('update', $user) ?? false;
@endphp

<x-layouts.app :title="$meta['title']" :description="$meta['description']"
               :canonical="\App\Support\Url::canonicalWithPage(['posts', 'comments'])">
    @push('head')
        <x-meta-og :meta="$meta" />
        <x-pagination-head :total="$postsTotal" :per-page="(int) config('orbita.pagination.profile_per_page')"
                           page-name="posts" />
    @endpush

    <div class="space-y-6">
        <div class="flex flex-col items-center gap-4 rounded-lg border border-gray-200 bg-white p-5 sm:flex-row sm:items-start dark:border-gray-800 dark:bg-gray-900">
            <x-avatar :user="$user" class="h-20 w-20 shrink-0" :alt="$displayName" />

            <div class="flex-1 text-center sm:text-left">
                <div class="flex items-center justify-center gap-1.5 sm:justify-start">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $displayName }}</h1>
                    @if ($user->website)
                        <a href="{{ $user->website }}" target="_blank" rel="nofollow noopener ugc"
                           title="Site de {{ $displayName }}"
                           class="text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
                            <x-heroicon-o-link class="h-4 w-4" aria-hidden="true" />
                        </a>
                    @endif
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">&#64;{{ $user->username }}</p>
                @if ($user->bio)
                    <p class="mt-2 text-gray-700 dark:text-gray-300">{{ $user->bio }}</p>
                @endif
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Membro desde {{ $user->created_at?->isoFormat('MMMM YYYY') }}
                </p>
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

        <div class="grid gap-6 md:grid-cols-2">
            <section>
                <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-gray-100">Posts</h2>
                <livewire:profile-posts :user-id="$user->id" />
            </section>

            <section>
                <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-gray-100">Comentários</h2>
                <livewire:profile-comments :user-id="$user->id" />
            </section>
        </div>
    </div>
</x-layouts.app>
