<x-layouts.app>
    <x-slot:title>Entrar - {{ config('orbita.name', 'Órbita') }}</x-slot:title>

    @if (request()->has('redirect_to'))
        @push('head')
            <meta name="robots" content="noindex, follow">
        @endpush
    @endif

    <div class="mx-auto max-w-md">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="space-y-4 p-6">
                <div>
                    <h1 class="font-serif text-2xl font-bold text-balance text-gray-900 dark:text-gray-100">Entrar no {{ config('orbita.name', 'Órbita') }}</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ config('orbita.meta.default_description') }}</p>
                </div>
                @php
                    $slugCopy = [
                        'verification-link-sent' => 'Se houver uma conta pendente com esse email, enviamos um novo link de ativação.',
                    ];
                    $status = (string) session('status');
                    $flash = session('success')
                        ?? ($slugCopy[$status] ?? (str_contains($status, ' ') ? $status : null));
                @endphp

                @if ($flash)
                    <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800 dark:bg-green-900/30 dark:text-green-200">
                        {{ $flash }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200">
                        {{ session('error') }}
                    </div>
                @endif

                <x-auth.errors :inline="['email', 'password']" />

                <x-auth.social-buttons />

                <form action="{{ route('login.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input type="email" id="email" name="email" {{ \App\Support\FormField::aria($errors, 'email') }} value="{{ old('email') }}" required autofocus autocomplete="email"
                               placeholder="seu@email.com"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 aria-[invalid=true]:border-red-500 dark:aria-[invalid=true]:border-red-400" />
                        <x-field-error name="email" />
                    </div>

                    <div>
                        <div class="mb-1 flex items-center justify-between">
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Senha</label>
                            <a href="{{ route('password.request') }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">Esqueci a senha</a>
                        </div>
                        <input type="password" id="password" name="password" {{ \App\Support\FormField::aria($errors, 'password') }} required autocomplete="current-password"
                               placeholder="Digite sua senha"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 aria-[invalid=true]:border-red-500 dark:aria-[invalid=true]:border-red-400" />
                        <x-field-error name="password" />
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        Manter conectado
                    </label>

                    <x-auth.turnstile />

                    <button type="submit" class="w-full rounded-md bg-primary-600 px-4 py-3 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                        Entrar
                    </button>
                </form>

                <p class="border-t border-gray-100 pt-4 text-center text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    Não tem uma conta?
                    <a href="{{ route('register') }}" wire:navigate class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Cadastre-se</a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>
