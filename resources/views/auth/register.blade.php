@php
    $termsPages = \App\Models\Page::query()
        ->where('is_active', true)
        ->where('is_terms', true)
        ->orderBy('title')
        ->get(['slug', 'title']);
@endphp
<x-layouts.app>
    <x-slot:title>Cadastrar - {{ config('orbita.name', 'Órbita') }}</x-slot:title>

    <div class="mx-auto max-w-md">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="bg-primary-600 px-6 py-4">
                <h1 class="text-lg font-semibold text-white">Criar conta</h1>
            </div>
            <div class="space-y-4 p-6">
                <x-auth.errors />

                <x-auth.social-buttons />

                <form action="{{ route('register.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email <span class="text-red-500">*</span></label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                               placeholder="seu@email.com"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                    </div>

                    <div>
                        <label for="username" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome de usuário <span class="text-gray-500">(opcional)</span></label>
                        <div class="flex rounded-md border border-gray-300 focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500 dark:border-gray-700">
                            <span class="inline-flex items-center rounded-l-md border-r border-gray-300 bg-gray-50 px-3 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">@</span>
                            <input type="text" id="username" name="username" value="{{ old('username') }}"
                                   pattern="[a-zA-Z0-9_-]{3,20}" placeholder="usuario123"
                                   class="w-full rounded-r-md bg-white px-3 py-2 text-sm text-gray-900 focus:outline-none dark:bg-gray-800 dark:text-gray-100" />
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">3-20 caracteres: letras, números, _ e - apenas. Se não informado, seu email será usado.</p>
                    </div>

                    <div>
                        <label for="display_name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome de exibição <span class="text-gray-500">(opcional)</span></label>
                        <input type="text" id="display_name" name="display_name" value="{{ old('display_name') }}" maxlength="100"
                               placeholder="Como seu nome aparecerá no site"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                    </div>

                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Senha <span class="text-red-500">*</span></label>
                        <input type="password" id="password" name="password" required autocomplete="new-password"
                               placeholder="Mínimo {{ config('orbita.password.min_length', 10) }} caracteres"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirmar senha <span class="text-red-500">*</span></label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                               placeholder="Repita a senha"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                    </div>

                    <x-auth.turnstile />

                    @if ($termsPages->isNotEmpty())
                        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                            Ao criar uma conta, você concorda com
                            @foreach ($termsPages as $i => $termsPage)@if ($i > 0){{ $i === $termsPages->count() - 1 ? ' e ' : ', ' }}@endif<a href="{{ route('pages.show', ['slug' => $termsPage->slug]) }}" wire:navigate class="font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ $termsPage->title }}</a>@endforeach.
                        </p>
                    @endif

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
