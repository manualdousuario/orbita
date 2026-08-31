<x-layouts.app>
    <x-slot:title>Editar comentário - {{ config('orbita.name', 'Órbita') }}</x-slot:title>

    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Editar comentário</h1>

    @if ($comment->edited_at)
        <p class="rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            Editado pela última vez em {{ $comment->edited_at->format('d/m/Y H:i') }}.
        </p>
    @endif

    <livewire:comment-form :edit-hashid="$comment->hashid" />

    <a href="{{ route('posts.show', ['hashid' => $comment->post->hashid, 'slug' => $comment->post->slug]) }}"
        class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400">
        <x-heroicon-o-arrow-left class="h-4 w-4" aria-hidden="true" />
        Voltar ao post
    </a>
</x-layouts.app>
