@php
    $providers = \App\Enums\SocialProvider::available();
@endphp

@if (! empty($providers))
    <div class="space-y-3">
        <div class="space-y-2">
            @foreach ($providers as $provider)
                <a href="{{ route('social.redirect', ['provider' => $provider->value]) }}"
                   class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-900">
                    <x-social-icon :provider="$provider" />
                    <span>Entrar com {{ $provider->label() }}</span>
                </a>
            @endforeach
        </div>

        <div class="flex items-center gap-3">
            <span class="h-px flex-1 bg-gray-200 dark:bg-gray-800"></span>
            <span class="text-sm uppercase tracking-wide text-gray-500 dark:text-gray-400">ou</span>
            <span class="h-px flex-1 bg-gray-200 dark:bg-gray-800"></span>
        </div>
    </div>
@endif
