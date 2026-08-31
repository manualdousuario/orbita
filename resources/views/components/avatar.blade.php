@props([
    'user',
    'class' => 'h-8 w-8',
    'alt' => null,
])

@php
    $isAnonymized = ($user->anonymized_at ?? null) !== null;

    $src = $isAnonymized
        ? \App\Support\Avatar::url('conta-excluida')
        : (filled($user->avatar_url)
            ? $user->avatar_url
            : \App\Support\Avatar::url((string) ($user->username ?? '')));

    $label = $isAnonymized
        ? \App\Actions\AnonymizeUser::DISPLAY_NAME
        : ($alt ?? ($user->display_name ?? $user->username ?? ''));
@endphp

<img src="{{ $src }}" alt="{{ $label }}" loading="lazy" decoding="async"
     class="{{ $class }} rounded-full object-cover bg-gray-100 dark:bg-gray-800">
