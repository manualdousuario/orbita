<?php

declare(strict_types=1);

/**
 * oEmbed providers must be fetched over https only.
 */

namespace Tests\Feature\Security;

use App\Services\OembedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

// fetchOembed() writes an oembed_cache row on every call, negative results included.
uses(RefreshDatabase::class);

it('no configured provider endpoint uses plain http', function () {
    $offenders = [];

    foreach ((array) config('orbita.oembed.providers') as $pattern => $definition) {
        $endpoint = is_array($definition) ? ($definition[0] ?? '') : $definition;

        if (! str_starts_with(strtolower((string) $endpoint), 'https://')) {
            $offenders[] = $pattern.' => '.$endpoint;
        }
    }

    expect($offenders)->toBe([], "oEmbed endpoints must be https:\n".implode("\n", $offenders));
});

/**
 * A plain-http provider is refused without any request being made.
 */
it('a plain http provider is refused without any request', function () {
    Http::preventStrayRequests();
    Http::fake();

    $result = app(OembedService::class)->fetchOembed(
        'https://example.com/video/1',
        'http://insecure.example/oembed',
    );

    expect($result)->toBeNull();
    Http::assertNothingSent();
});

it('an https provider is still fetched', function () {
    Http::preventStrayRequests();
    Http::fake([
        'secure.example/*' => Http::response(['html' => '<iframe src="https://secure.example/e/1"></iframe>'], 200),
    ]);

    $result = app(OembedService::class)->fetchOembed(
        'https://example.com/video/2',
        'https://secure.example/oembed',
    );

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('html');
});
