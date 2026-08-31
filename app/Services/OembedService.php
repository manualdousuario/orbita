<?php

namespace App\Services;

use App\Support\OutboundHttp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Resolves URLs to oEmbed endpoints, fetches embed data, and caches results.
 */
class OembedService
{
    /** @var array<string, array{0: string, 1: bool}> */
    private array $providers;

    public function __construct()
    {
        $this->providers = config('orbita.oembed.providers', []);
    }

    /**
     * @return array{provider: string, discoverable: bool}|null
     */
    public function checkUrl(string $url): ?array
    {
        foreach ($this->providers as $pattern => $provider) {
            if (preg_match($pattern, $url)) {
                return [
                    'provider' => $provider[0],
                    'discoverable' => $provider[1],
                ];
            }
        }

        return null;
    }

    /**
     * Fetches oEmbed data for a URL, using the cache while it is fresh.
     *
     * @return array<string, mixed>|null
     */
    public function fetchOembed(string $url, string $provider): ?array
    {
        $record = $this->cachedRecord($url);

        if ($record !== null) {
            $decoded = json_decode((string) $record->oembed_data, true);

            return is_array($decoded) && $decoded !== [] ? $decoded : null;
        }

        $oembedData = $this->fetchFromProvider($url, $provider);

        $this->upsertCache($url, $oembedData);

        return $oembedData;
    }

    /**
     * The fresh cache row for a URL, success or failure alike.
     */
    private function cachedRecord(string $url): ?object
    {
        return DB::table('oembed_cache')
            ->where('url_hash', md5($url))
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * @param  array<string, mixed>|null  $oembedData  null when the fetch failed
     */
    private function upsertCache(string $url, ?array $oembedData): void
    {
        $succeeded = is_array($oembedData) && $oembedData !== [];

        $expiresAt = $succeeded
            ? now()->addDays((int) config('orbita.oembed.cache_ttl_days', 7))
            : now()->addMinutes((int) config('orbita.oembed.failure_ttl_minutes', 60));

        DB::table('oembed_cache')->updateOrInsert(
            ['url_hash' => md5($url)],
            [
                'url' => $url,
                'oembed_data' => $succeeded ? json_encode($oembedData) : '[]',
                'expires_at' => $expiresAt,
                'created_at' => now(),
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchFromProvider(string $url, string $provider): ?array
    {
        $provider = str_replace('{format}', 'json', $provider);

        if (! str_starts_with(strtolower($provider), 'https://')) {
            Log::warning('Oembed provider rejected: endpoint is not https', [
                'url' => $url,
                'provider' => $provider,
            ]);

            return null;
        }

        $oembedUrl = $provider.(strpos($provider, '?') !== false ? '&' : '?').'url='.urlencode($url);

        if (strpos($oembedUrl, 'format=') === false) {
            $oembedUrl .= '&format=json';
        }

        try {
            // Inline fetch on first render, so a slow provider must not hold the page hostage.
            $response = OutboundHttp::send(fn (PendingRequest $http) => $http
                ->timeout(3)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; Orbita/1.0)',
                ])
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => true,
                ])
                ->get($oembedUrl));

            if ($response->status() === 200) {
                $data = $response->json();

                if (is_array($data)) {
                    return $data;
                }
            }
        } catch (\Throwable $e) {
            Log::error('Oembed fetch failed', [
                'url' => $url,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
