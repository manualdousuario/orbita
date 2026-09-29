@props([
    'inline' => [],
])

@if ($errors->any())
    @php
        $remaining = collect($errors->getMessages())
            ->except($inline)
            ->flatten()
            ->all();
    @endphp

    <div role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-900/30 dark:text-red-200">
        @if ($remaining === [])
            <p>Não deu para continuar. Confira os campos marcados abaixo.</p>
        @elseif (count($remaining) === 1)
            <p>{{ $remaining[0] }}</p>
        @else
            <ul class="list-inside list-disc space-y-1">
                @foreach ($remaining as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
