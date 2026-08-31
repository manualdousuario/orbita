@php
    $turnstileEnabled = config('orbita.turnstile.enabled') && config('orbita.turnstile.site_key');
@endphp

@if ($turnstileEnabled)
    <div class="cf-turnstile" data-sitekey="{{ config('orbita.turnstile.site_key') }}"></div>

    @once
        @push('scripts')
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endpush
    @endonce
@endif
