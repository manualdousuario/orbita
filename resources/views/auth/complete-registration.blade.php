<x-layouts.app>
    <x-slot:title>Completar cadastro - {{ config('orbita.name', 'Órbita') }}</x-slot:title>

    <div class="mx-auto max-w-md">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="space-y-4 p-6">
                <h1 class="font-serif text-2xl font-bold text-balance text-gray-900 dark:text-gray-100">Falta pouco</h1>
                <x-auth.errors :inline="['email', 'username']" />

                <div class="flex items-center gap-3 rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
                    @if ($profile->avatarUrl)
                        <img src="{{ $profile->avatarUrl }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-cover" />
                    @endif
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $profile->nickname ?: $profile->name ?: 'Conta conectada' }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Conectado via {{ $profile->provider->label() }}</p>
                    </div>
                </div>

                <p class="text-sm text-gray-600 dark:text-gray-400">
                    O {{ $profile->provider->label() }} não informa seu email, então precisamos que você digite um.
                    Enviaremos um link de ativação para confirmar que o endereço é seu.
                </p>

                <form action="{{ route('social.complete.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email <span class="text-red-500">*</span></label>
                        <input type="email" id="email" name="email" {{ \App\Support\FormField::aria($errors, 'email') }} value="{{ old('email') }}" required autofocus autocomplete="email"
                               placeholder="seu@email.com"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 aria-[invalid=true]:border-red-500 dark:aria-[invalid=true]:border-red-400" />
                        <x-field-error name="email" />
                    </div>

                    <div>
                        <label for="username" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome de usuário <span class="text-gray-500">(opcional)</span></label>
                        <div class="flex rounded-md border border-gray-300 has-[[aria-invalid=true]]:border-red-500 dark:has-[[aria-invalid=true]]:border-red-400 focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500 dark:border-gray-700">
                            <span class="inline-flex items-center rounded-l-md border-r border-gray-300 bg-gray-50 px-3 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">@</span>
                            <input type="text" id="username" name="username" {{ \App\Support\FormField::aria($errors, 'username', 'username-hint') }} value="{{ old('username', $suggestedUsername) }}"
                                   maxlength="50" placeholder="usuario123"
                                   class="w-full rounded-r-md bg-white px-3 py-2 text-sm text-gray-900 focus:outline-none dark:bg-gray-800 dark:text-gray-100" />
                        </div>
                        <p id="username-hint" class="mt-1 text-sm text-gray-500 dark:text-gray-400">Letras, números e _. Se deixar em branco, criamos um a partir do começo do seu email; o email em si nunca aparece no site.</p>
                        <x-field-error name="username" />
                    </div>

                    <x-auth.turnstile />

                    <button type="submit" class="w-full rounded-md bg-primary-600 px-4 py-3 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                        Criar conta
                    </button>
                </form>

                <p class="border-t border-gray-100 pt-4 text-center text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    Já tem uma conta?
                    <a href="{{ route('login') }}" wire:navigate class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Faça login</a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>
