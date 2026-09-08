@props(['locked' => false])

@if ($locked)
    <span {{ $attributes->class(['mr-1 inline-flex h-[1lh] items-center align-top text-gray-500 dark:text-gray-400']) }}
          title="Comentários fechados">
        <x-icon-lock class="h-4 w-4" aria-hidden="true" />
        <span class="sr-only">Comentários fechados</span>
    </span>
@endif
