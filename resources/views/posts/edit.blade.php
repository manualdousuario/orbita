<x-layouts.app>
    <x-slot:title>Editar post - {{ config('orbita.name', 'Órbita') }}</x-slot:title>

    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Editar post</h1>

    <livewire:post-composer :post="$post" />
</x-layouts.app>
