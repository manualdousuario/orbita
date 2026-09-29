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
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="space-y-4 p-6">
                <div>
                    <h1 class="font-serif text-2xl font-bold text-balance text-gray-900 dark:text-gray-100">Criar conta no {{ config('orbita.name', 'Órbita') }}</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ config('orbita.meta.default_description') }}</p>
                </div>
                <x-auth.errors :inline="['email', 'username', 'display_name', 'password', 'password_confirmation']" />

                <x-auth.social-buttons />

                <form action="{{ route('register.store') }}" method="POST" class="space-y-4">
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
                            <input type="text" id="username" name="username" {{ \App\Support\FormField::aria($errors, 'username', 'username-hint') }} value="{{ old('username') }}"
                                   pattern="[a-zA-Z0-9_-]{3,20}" placeholder="usuario123" autocomplete="username"
                                   class="w-full rounded-r-md bg-white px-3 py-2 text-sm text-gray-900 focus:outline-none dark:bg-gray-800 dark:text-gray-100" />
                        </div>
                        <p id="username-hint" class="mt-1 text-sm text-gray-500 dark:text-gray-400">De 3 a 20 caracteres: letras, números, _ e -. Se deixar em branco, criamos um a partir do começo do seu email; o email em si nunca aparece no site.</p>
                        <x-field-error name="username" />
                    </div>

                    <div>
                        <label for="display_name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome de exibição <span class="text-gray-500">(opcional)</span></label>
                        <input type="text" id="display_name" name="display_name" {{ \App\Support\FormField::aria($errors, 'display_name') }} value="{{ old('display_name') }}" maxlength="100" autocomplete="nickname"
                               placeholder="Como seu nome aparecerá no site"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 aria-[invalid=true]:border-red-500 dark:aria-[invalid=true]:border-red-400" />
                        <x-field-error name="display_name" />
                    </div>

                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Senha <span class="text-red-500">*</span></label>
                        <input type="password" id="password" name="password" {{ \App\Support\FormField::aria($errors, 'password', 'password-hint') }} required autocomplete="new-password"
                               minlength="{{ config('orbita.password.min_length', 10) }}"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 aria-[invalid=true]:border-red-500 dark:aria-[invalid=true]:border-red-400" />
                        <p id="password-hint" class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pelo menos {{ config('orbita.password.min_length', 10) }} caracteres.</p>
                        <x-field-error name="password" />
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirmar senha <span class="text-red-500">*</span></label>
                        <input type="password" id="password_confirmation" name="password_confirmation" {{ \App\Support\FormField::aria($errors, 'password_confirmation') }} required autocomplete="new-password"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 aria-[invalid=true]:border-red-500 dark:aria-[invalid=true]:border-red-400" />
                        <x-field-error name="password_confirmation" />
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
