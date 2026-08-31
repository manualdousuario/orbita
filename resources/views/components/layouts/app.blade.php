@props(['title' => null, 'description' => null, 'canonical' => null, 'fab' => true])

@php
    $brand = config('orbita.name', 'Órbita');
    $pageTitle = $title ?? $brand;
    $quote = \App\Support\Quotes::random();
    $user = auth()->user();
    $footerPages = \Illuminate\Support\Facades\Cache::remember(
        \App\Models\Page::FOOTER_CACHE_KEY,
        now()->addDay(),
        fn () => \App\Models\Page::query()
            ->where('is_active', true)
            ->where('show_in_footer', true)
            ->orderBy('title')
            ->pluck('title', 'slug')
            ->all(),
    );
@endphp
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="{{ $description ?? '' }}">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">

    <title>{{ $pageTitle }}</title>

    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="Órbita" />
    <link rel="manifest" href="/site.webmanifest" />

    @stack('head')

    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme') || 'auto';
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                var isDark = t === 'dark' || (t === 'auto' && prefersDark);
                document.documentElement.classList.toggle('dark', isDark);
            } catch (e) {}
            document.documentElement.classList.add('js');
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if (filled(config('orbita.meta.third_party_code')))
        {!! config('orbita.meta.third_party_code') !!}
    @endif
</head>

<body class="min-h-full flex flex-col bg-gray-50 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <a href="#conteudo"
       class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-primary-600 focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary-300">
        Pular para o conteúdo
    </a>

    <header class="sticky top-0 z-40 border-b border-gray-200 bg-white/90 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90">
        <div class="mx-auto flex max-w-[900px] items-center gap-3 px-4 py-3">
            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2 text-lg font-semibold">
                {{-- overflow-visible: a órbita do satélite passa ~1px fora do viewBox --}}
                <svg class="h-6 w-6 overflow-visible text-primary-600 dark:text-primary-400" viewBox="0 0 450 470" fill="currentColor" aria-hidden="true">
                    <path d="M225 19.42C100.55 19.42-.34 120.31-.34 244.76S100.55 470.1 225 470.1s225.34-100.89 225.34-225.34S349.45 19.42 225 19.42m0 401.3c-97.18 0-175.95-78.78-175.95-175.95S127.83 68.82 225 68.82s175.95 78.78 175.95 175.95S322.17 420.72 225 420.72" />
                    <circle cx="228.09" cy="247.85" r="117.3" />
                    <circle id="orbita-moon" class="origin-[225px_244.76px] [transform-box:view-box]"
                            cx="302.17" cy="62.63" r="61.74" />
                </svg>
                <span class="hidden sm:inline text-black dark:text-white">{{ $brand }}</span>
            </a>

            <nav class="ml-2 hidden items-center gap-1 md:flex">
                <a href="{{ route('posts.create') }}" wire:navigate class="flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-primary-600 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-primary-400">
                    <x-heroicon-o-plus class="h-4 w-4" aria-hidden="true" />
                    Novo post
                </a>
            </nav>

            <form action="{{ route('search') }}" method="GET" role="search" class="ml-auto hidden min-w-0 flex-1 sm:block sm:max-w-xs">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <x-heroicon-o-magnifying-glass class="h-4 w-4" aria-hidden="true" />
                    </span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar..." aria-label="Buscar"
                           class="w-full rounded-md border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                </div>
            </form>

            <div class="ml-auto flex items-center gap-1 sm:ml-2">
                <a href="{{ route('search') }}" wire:navigate aria-label="Buscar"
                   @if (request()->routeIs('search')) aria-current="page" @endif
                   class="flex h-11 w-11 items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 sm:hidden dark:text-gray-300 dark:hover:bg-gray-800">
                    <x-heroicon-o-magnifying-glass class="h-5 w-5" aria-hidden="true" />
                </a>

                @auth
                    <livewire:notifications-bell />

                    <div class="relative" x-data="{ open: false }" @keydown.escape="open = false; $refs.trigger.focus()">
                        <button type="button" x-ref="trigger" @click="open = !open" aria-label="Menu do usuário"
                                class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">
                            <x-avatar :user="$user" class="h-8 w-8" />
                            <span class="hidden lg:inline">{{ $user->display_name ?? $user->username }}</span>
                        </button>
                        <div x-show="open" x-transition @click.outside="open = false" x-cloak
                             class="absolute right-0 mt-2 w-48 overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                            @if ($user->isStaff())
                                <a href="/admin" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                    <x-heroicon-o-wrench-screwdriver class="h-4 w-4" aria-hidden="true" />
                                    Admin
                                </a>
                                <div class="border-t border-gray-100 dark:border-gray-700"></div>
                            @endif
                            <a href="{{ route('users.profile', ['username' => $user->username]) }}" wire:navigate class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                <x-heroicon-o-user class="h-4 w-4" aria-hidden="true" />
                                Perfil
                            </a>
                            <a href="{{ route('bookmarks.index') }}" wire:navigate class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                <x-heroicon-o-bookmark class="h-4 w-4" aria-hidden="true" />
                                Acompanhando
                            </a>
                            <a href="{{ route('users.edit', ['username' => $user->username]) }}" wire:navigate class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                <x-heroicon-o-cog-6-tooth class="h-4 w-4" aria-hidden="true" />
                                Configurações
                            </a>
                            <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm text-red-600 hover:bg-gray-100 dark:text-red-400 dark:hover:bg-gray-700">
                                    <x-heroicon-o-arrow-right-start-on-rectangle class="h-4 w-4" aria-hidden="true" />
                                    Sair
                                </button>
                            </form>
                        </div>
                    </div>
                @endauth

                @guest
                    <a href="{{ route('login', ['redirect_to' => url()->current()]) }}" wire:navigate class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">Entrar</a>
                    <a href="{{ route('register') }}" wire:navigate class="rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-700">Cadastrar</a>
                @endguest
            </div>
        </div>
    </header>

    @if (in_array(session('status'), ['verification-completed', 'verification-already-active', 'verification-completed-other'], true))
        <div class="border-b border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/40">
            <div class="mx-auto flex max-w-[900px] items-center gap-x-3 px-4 py-3 text-sm text-green-800 dark:text-green-200">
                <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" aria-hidden="true" />
                <p class="min-w-0 flex-1">
                    @if (session('status') === 'verification-completed')
                        Conta ativada com sucesso! Agora você pode publicar, comentar e reagir.
                    @elseif (session('status') === 'verification-already-active')
                        Sua conta já estava ativa. Tudo certo!
                    @else
                        A conta <strong>{{ session('verification.masked_email') }}</strong> foi ativada.
                        Você está conectado como <strong>{{ '@'.$user?->username }}</strong>. Saia e entre com a conta ativada para usá-la.
                    @endif
                </p>
            </div>
        </div>
    @elseif ($user?->isPendingActivation())
        <div class="border-b border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40">
            <div class="mx-auto flex max-w-[900px] flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
                @if (session('status') === 'verification-link-sent')
                    <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" aria-hidden="true" />
                    <p class="min-w-0 flex-1">
                        Enviamos um novo link de ativação para <strong>{{ $user->email }}</strong>. O link vale 24 horas.
                    </p>
                @else
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" aria-hidden="true" />
                    <p class="min-w-0 flex-1">
                        Sua conta ainda não foi ativada. Você pode ler tudo, mas não publicar, comentar ou reagir.
                        Confira o link que enviamos para <strong>{{ $user->email }}</strong>.
                    </p>
                    <form action="{{ route('verification.send') }}" method="POST" class="shrink-0">
                        @csrf
                        <button type="submit" class="rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                            Reenviar email
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <main id="conteudo" tabindex="-1" class="mx-auto w-full max-w-[900px] flex-1 px-4 py-6 focus:outline-none">
        {{ $slot }}
    </main>

    <footer class="border-t border-gray-200 py-4 dark:border-gray-800">
        <div class="mx-auto flex max-w-[900px] flex-col items-end gap-2 px-4 text-right text-sm text-gray-500 dark:text-gray-400">
            <div class="flex flex-wrap items-center justify-end gap-x-4 gap-y-1">
                @if ($footerPages !== [])
                    <nav class="flex flex-wrap items-center justify-end gap-x-3 gap-y-1" aria-label="Páginas">
                        @foreach ($footerPages as $footerSlug => $footerTitle)
                            <a href="{{ route('pages.show', ['slug' => $footerSlug]) }}" wire:navigate class="text-primary-600 underline underline-offset-2 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">{{ $footerTitle }}</a>
                        @endforeach
                    </nav>
                @endif

                <a href="{{ url('/feed') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-primary-600 underline underline-offset-2 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300" title="RSS Feed">
                    <x-heroicon-o-rss class="h-4 w-4" aria-hidden="true" />
                    Feed
                </a>

                <div x-data="themeSwitcher" role="group" aria-label="Tema"
                     class="inline-flex overflow-hidden rounded-md border border-gray-300 dark:border-gray-700">
                    <button type="button" @click="set('light')"
                            :aria-pressed="theme === 'light' ? 'true' : 'false'"
                            :class="theme === 'light'
                                ? 'bg-gray-200 text-gray-900 dark:bg-gray-700 dark:text-gray-100'
                                : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'"
                            class="inline-flex min-h-[26px] items-center gap-1 px-2 text-[11px] font-medium transition">
                        <x-heroicon-o-sun class="h-3.5 w-3.5" aria-hidden="true" />
                        Claro
                    </button>
                    <button type="button" @click="set('dark')"
                            :aria-pressed="theme === 'dark' ? 'true' : 'false'"
                            :class="theme === 'dark'
                                ? 'bg-gray-200 text-gray-900 dark:bg-gray-700 dark:text-gray-100'
                                : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'"
                            class="inline-flex min-h-[26px] items-center gap-1 border-l border-gray-300 px-2 text-[11px] font-medium transition dark:border-gray-700">
                        <x-heroicon-o-moon class="h-3.5 w-3.5" aria-hidden="true" />
                        Escuro
                    </button>
                    <button type="button" @click="set('auto')"
                            :aria-pressed="theme === 'auto' ? 'true' : 'false'"
                            aria-label="Automático: seguir o tema do sistema"
                            title="Automático: seguir o tema do sistema"
                            :class="theme === 'auto'
                                ? 'bg-gray-200 text-gray-900 dark:bg-gray-700 dark:text-gray-100'
                                : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'"
                            class="inline-flex min-h-[26px] items-center justify-center border-l border-gray-300 px-2 transition dark:border-gray-700">
                        <x-heroicon-o-computer-desktop class="h-3.5 w-3.5" aria-hidden="true" />
                    </button>
                </div>
            </div>

            @if ($quote)
                <p class="italic">{{ $quote['text'] }}@if ($quote['author'] !== '') - {{ $quote['author'] }}@endif</p>
            @endif
        </div>
    </footer>

    @include('partials.compose-fab')
    @include('partials.toast')

    @stack('scripts')
</body>

</html>
