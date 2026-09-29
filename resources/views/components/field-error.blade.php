@props(['name'])

@error($name)
    <p id="{{ \App\Support\FormField::errorId($name) }}" {{ $attributes->class(['mt-1 flex items-start gap-1 text-sm text-red-700 dark:text-red-300']) }}>
        <x-heroicon-s-exclamation-circle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
        <span>{{ $message }}</span>
    </p>
@enderror
