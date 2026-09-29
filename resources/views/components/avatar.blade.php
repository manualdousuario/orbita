@props([
    'user',
    'class' => 'h-8 w-8',
    'alt' => null,
])

@php
    $isAnonymized = ($user->anonymized_at ?? null) !== null;

    $generated = \App\Support\Avatar::url($isAnonymized ? 'conta-excluida' : (string) ($user->username ?? ''));

    $src = ! $isAnonymized && filled($user->avatar_url) ? $user->avatar_url : $generated;

    $label = $isAnonymized
        ? \App\Actions\AnonymizeUser::DISPLAY_NAME
        : ($alt ?? ($user->display_name ?? $user->username ?? ''));
@endphp

<img src="{{ $src }}" alt="{{ $label }}" loading="lazy" decoding="async"
     @if ($src !== $generated) onerror="this.onerror = null; this.src = @js($generated)" @endif
     class="{{ $class }} rounded-full object-cover bg-gray-100 text-transparent dark:bg-gray-800">
