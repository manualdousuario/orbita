<x-layouts.app :title="'Acompanhando - '.config('orbita.name', 'Órbita')">
    @push('head')
        <meta name="robots" content="noindex, nofollow">
    @endpush

    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Acompanhando</h1>

    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
        Ao acompanhar um post, ele fica listado nesta página e você passa a receber notificações no site e por e-mail de todos os novos comentários publicados.
    </p>

    <livewire:bookmarked-posts />
</x-layouts.app>
