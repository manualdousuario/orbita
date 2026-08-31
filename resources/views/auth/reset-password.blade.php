<x-layouts.app>
    <x-slot:title>Redefinir senha - {{ config('orbita.name', 'Órbita') }}</x-slot:title>

    <div class="mx-auto max-w-md">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="bg-primary-600 px-6 py-4">
                <h1 class="text-lg font-semibold text-white">Redefinir senha</h1>
            </div>
            <div class="space-y-4 p-6">
                <x-auth.errors />

                <p class="text-sm text-gray-600 dark:text-gray-400">Digite sua nova senha.</p>

                <form action="{{ route('password.update') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="token" value="{{ request()->route('token') }}">

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email', request('email')) }}" required autocomplete="email"
                               placeholder="seu@email.com"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                    </div>

                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Nova senha</label>
                        <input type="password" id="password" name="password" required autofocus autocomplete="new-password"
                               placeholder="Digite sua nova senha"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">A senha deve ter no mínimo {{ config('orbita.password.min_length', 10) }} caracteres.</p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirmar nova senha</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                               placeholder="Repita a nova senha"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                    </div>

                    <button type="submit" class="w-full rounded-md bg-primary-600 px-4 py-3 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                        Redefinir senha
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
