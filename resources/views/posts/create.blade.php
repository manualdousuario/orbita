<x-layouts.app>
    <x-slot:title>Novo post - {{ config('orbita.name', 'Órbita') }}</x-slot:title>

    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Criar novo post</h1>

    <livewire:post-composer />
</x-layouts.app>
