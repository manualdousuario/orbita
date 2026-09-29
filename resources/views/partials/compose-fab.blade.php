@php
    $fabRoutes = ['home', 'home.*', 'posts.show', 'tags.show', 'search'];
@endphp

@if (($fab ?? true) && request()->routeIs($fabRoutes))
    @php
        $user = auth()->user();
    @endphp

    <a href="{{ $user ? route('posts.create') : route('login', ['redirect_to' => url()->current()]) }}" rel="nofollow" wire:navigate
       x-data="composeFab"
       x-bind:inert="! shown"
       x-bind:class="shown ? 'opacity-100' : 'opacity-0 translate-y-4 pointer-events-none'"
       x-bind:style="{ bottom: `calc(1.5rem + ${lift}px + env(safe-area-inset-bottom, 0px))` }"
       class="fixed right-4 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-[0_6px_16px_-4px_rgba(0,0,0,0.35)] transition duration-200 hover:bg-primary-700 motion-reduce:transition-none md:hidden dark:shadow-[0_6px_16px_-4px_rgba(0,0,0,0.7)]"
       style="bottom: calc(1.5rem + env(safe-area-inset-bottom, 0px));">
        <x-heroicon-o-plus class="h-7 w-7" aria-hidden="true" stroke-width="2.2" />
        <span class="sr-only">Novo post</span>
    </a>
@endif
