<x-layouts.app>
    <x-slot:title>Ative sua conta - {{ config('orbita.name', 'Órbita') }}</x-slot:title>

    <div class="mx-auto max-w-md">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="bg-primary-600 px-6 py-4">
                <h1 class="text-lg font-semibold text-white">Ative sua conta</h1>
            </div>
            <div class="space-y-4 p-6">
                @if (session('status') === 'verification-link-sent')
                    <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800 dark:bg-green-900/30 dark:text-green-200">
                        Se houver uma conta pendente com esse email, enviamos um novo link de ativação. O link vale 24 horas.
                    </div>
                @elseif (session('status') === 'verification-link-expired')
                    <div class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                        Esse link de ativação expirou (validade de 24 horas). Clique abaixo para receber um novo.
                    </div>
                @elseif (session('status') === 'verification-required')
                    <div class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                        Para publicar, comentar ou reagir você precisa ativar sua conta. Clique abaixo para receber um novo link.
                    </div>
                @elseif (session('status') === 'verification-link-stale')
                    <div class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                        Esse link não vale mais porque o email da conta foi alterado depois do envio. Podemos enviar um novo link para o endereço atual.
                    </div>
                @elseif (session('status') === 'verification-throttled')
                    <div class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                        Recebemos acessos demais a esse link em pouco tempo. Aguarde alguns minutos e tente de novo.
                    </div>
                @elseif (session('status') === 'verification-link-invalid')
                    <div class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                        Não foi possível confirmar esse link. Clique abaixo para receber um novo.
                    </div>
                @endif

                <p class="text-sm text-gray-600 dark:text-gray-400">
                    @if ($email)
                        Enviamos um link de ativação para <strong>{{ $email }}</strong>. Se não recebeu, podemos enviar outro.
                    @else
                        Antes de continuar, ative sua conta pelo link que enviamos por email. Se não recebeu, podemos enviar outro.
                    @endif
                </p>

                <form action="{{ route('verification.send') }}" method="POST" class="space-y-3">
                    @csrf

                    @unless ($knowsWhoYouAre)
                        <div>
                            <label for="email" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Seu email</label>
                            <input type="email" id="email" name="email" required value="{{ old('email') }}"
                                   placeholder="seu@email.com"
                                   class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                        </div>

                        <x-auth.errors />
                        <x-auth.turnstile />
                    @endunless

                    <button type="submit" class="w-full rounded-md bg-primary-600 px-4 py-3 text-sm font-semibold text-white hover:bg-primary-700">
                        Reenviar email de ativação
                    </button>
                </form>

                @auth
                    <form action="{{ route('logout') }}" method="POST" class="text-center">
                        @csrf
                        <button type="submit" class="text-sm text-gray-500 hover:underline dark:text-gray-400">Sair</button>
                    </form>
                @else
                    <p class="border-t border-gray-100 pt-4 text-center text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        Já ativou sua conta?
                        <a href="{{ route('login') }}" wire:navigate class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Entrar</a>
                    </p>
                @endauth
            </div>
        </div>
    </div>
</x-layouts.app>
