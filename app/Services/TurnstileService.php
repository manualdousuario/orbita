<?php

namespace App\Services;

use App\Support\OutboundHttp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;

/**
 * Validates Cloudflare Turnstile tokens server-side to protect public forms from spam.
 */
class TurnstileService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private string $siteKey;

    private string $secretKey;

    private bool $enabled;

    public function __construct()
    {
        $this->siteKey = (string) config('orbita.turnstile.site_key', '');
        $this->secretKey = (string) config('orbita.turnstile.secret_key', '');
        $this->enabled = (bool) config('orbita.turnstile.enabled', false)
            && $this->siteKey !== '' && $this->secretKey !== '';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getSiteKey(): string
    {
        return $this->siteKey;
    }

    public function validate(string $token, string $remoteIp = ''): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if ($token === '') {
            Log::warning('Turnstile validation failed - empty token');

            return false;
        }

        try {
            $response = OutboundHttp::send(fn (PendingRequest $http) => $http->asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => $this->secretKey,
                'response' => $token,
                'remoteip' => $remoteIp,
            ]));

            if (! $response->successful()) {
                Log::error('Turnstile API request failed', ['status' => $response->status()]);

                return false;
            }

            $data = $response->json();

            if (! is_array($data) || ! ($data['success'] ?? false)) {
                Log::warning('Turnstile validation failed', ['codes' => $data['error-codes'] ?? []]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Turnstile validation failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
