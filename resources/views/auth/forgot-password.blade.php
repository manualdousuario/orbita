<x-layouts.app>
    <x-slot:title>Recuperar senha - {{ config('orbita.name', 'Órbita') }}</x-slot:title>

    <div class="mx-auto max-w-md">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="bg-primary-600 px-6 py-4">
                <h1 class="text-lg font-semibold text-white">Recuperar senha</h1>
            </div>
            <div class="space-y-4 p-6">
                @if (session('status'))
                    <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800 dark:bg-green-900/30 dark:text-green-200">
                        {{ session('status') }}
                    </div>
                @endif

                <x-auth.errors />

                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Digite seu email cadastrado e enviaremos um link para redefinir sua senha.
                </p>

                <form action="{{ route('password.email') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                               placeholder="seu@email.com"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                    </div>

                    <button type="submit" class="w-full rounded-md bg-primary-600 px-4 py-3 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                        Enviar link de recuperação
                    </button>
                </form>

                <p class="border-t border-gray-100 pt-4 text-center text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    Lembrou sua senha?
                    <a href="{{ route('login') }}" wire:navigate class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Voltar para o login</a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>
