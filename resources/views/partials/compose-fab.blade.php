@php
    $fabRoutes = ['home', 'home.*', 'posts.show', 'tags.show', 'search'];
@endphp

@if (($fab ?? true) && request()->routeIs($fabRoutes))
    @php
        $user = auth()->user();
    @endphp

    <a href="{{ $user ? route('posts.create') : route('login', ['redirect_to' => url()->current()]) }}" wire:navigate
       x-data="composeFab"
       x-bind:inert="! visible"
       x-bind:class="visible ? 'opacity-100' : 'opacity-0 translate-y-4 pointer-events-none'"
       x-bind:style="{ bottom: `calc(1.5rem + ${lift}px + env(safe-area-inset-bottom, 0px))` }"
       class="fixed right-4 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-[0_18px_50px_-8px_rgba(79,70,229,0.55)] transition duration-200 hover:bg-primary-700 md:hidden dark:shadow-[0_18px_50px_-8px_rgba(99,102,241,0.45)]"
       style="bottom: calc(1.5rem + env(safe-area-inset-bottom, 0px));">
        <x-heroicon-o-plus class="h-7 w-7" aria-hidden="true" stroke-width="2.2" />
        <span class="sr-only">Publicar</span>
    </a>
@endif
