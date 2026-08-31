<x-layouts.app>
    <x-slot:title>Confirmar exclusão da conta - {{ config('orbita.name', 'Órbita') }}</x-slot:title>

    <div class="mx-auto max-w-md">
        <div class="overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm dark:border-red-900 dark:bg-gray-900">
            <div class="bg-red-600 px-6 py-4">
                <h1 class="text-lg font-semibold text-white">Confirmar exclusão da conta</h1>
            </div>

            <div class="space-y-4 p-6">
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Você está prestes a excluir a conta
                    <strong class="text-gray-900 dark:text-gray-100">&#64;{{ $user?->username }}</strong>.
                </p>

                <div class="rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    <p class="font-medium text-gray-900 dark:text-gray-100">O que acontece ao confirmar</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>Seus posts e comentários <strong>continuam publicados</strong>, mas passam a aparecer como "Conta excluída", sem link para o seu perfil.</li>
                        <li>Nome, email, avatar, bio, favoritos e notificações são apagados.</li>
                        <li>Seu email e nome de usuário ficam livres para um novo cadastro.</li>
                        <li>Você é desconectado de todos os dispositivos.</li>
                    </ul>
                </div>

                <p class="text-sm font-semibold text-red-700 dark:text-red-400">Esta ação é irreversível.</p>

                <form action="{{ route('users.delete-account.execute', ['token' => $token]) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="w-full rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                        Excluir minha conta definitivamente
                    </button>
                </form>

                <p class="text-center text-sm">
                    <a href="{{ route('home') }}" class="text-gray-600 hover:underline dark:text-gray-400">Cancelar</a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>
