@props([
    'hashid',
    'bookmarked' => false,
    'compact' => false,
])

@php
    $user = auth()->user();
    $mustActivate = $user?->isPendingActivation() ?? false;

    $base = 'inline-flex items-center gap-1.5 rounded-md text-sm font-medium transition';
    $shape = $compact ? 'justify-center bg-gray-100 p-3.5 dark:bg-gray-800' : 'py-3';

    $onColors = $compact
        ? 'text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-950'
        : 'text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300';
    $offColors = $compact
        ? 'text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100'
        : 'text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400';

    $label = $bookmarked ? 'Deixar de acompanhar' : 'Acompanhar post';

    $solidClass = 'h-4 w-4'.($bookmarked ? '' : ' hidden');
    $outlineClass = 'h-4 w-4'.($bookmarked ? ' hidden' : '');
@endphp

@if (! $user)
    <a href="{{ route('login', ['redirect_to' => url()->current()]) }}"
       aria-label="Entrar para acompanhar posts"
       class="{{ $base }} {{ $shape }} text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
        <x-heroicon-o-bookmark class="h-4 w-4" aria-hidden="true" />
    </a>
@elseif ($mustActivate)
    <a href="{{ route('verification.notice') }}"
       aria-label="Ative sua conta para acompanhar posts"
       class="{{ $base }} {{ $shape }} text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
        <x-heroicon-o-bookmark class="h-4 w-4" aria-hidden="true" />
    </a>
@else
    <button type="button"
            x-data="bookmarkButton({ url: '{{ route('bookmarks.toggle', ['hashid' => $hashid]) }}', bookmarked: {{ $bookmarked ? 'true' : 'false' }} })"
            @click="toggle()"
            aria-pressed="{{ $bookmarked ? 'true' : 'false' }}"
            aria-label="{{ $label }}"
            x-bind:class="{ '{{ $onColors }}': bookmarked, '{{ $offColors }}': ! bookmarked }"
            class="{{ $base }} {{ $shape }} min-w-4 {{ $bookmarked ? $onColors : $offColors }}">
        <x-heroicon-s-bookmark x-bind:class="{ 'hidden': ! bookmarked }"
                               class="{{ $solidClass }}" aria-hidden="true" />
        <x-heroicon-o-bookmark x-bind:class="{ 'hidden': bookmarked }"
                               class="{{ $outlineClass }}" aria-hidden="true" />
    </button>
@endif
