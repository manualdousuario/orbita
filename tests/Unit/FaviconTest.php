<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Favicon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('returns the icon url when duckduckgo has one', function () {
    Http::fake(['icons.duckduckgo.com/*' => Http::response('', 200)]);

    expect(Favicon::url('github.com'))->toBe('https://icons.duckduckgo.com/ip3/github.com.ico');
});

it('returns null on 404, whose body is a placeholder image the browser would render', function () {
    Http::fake(['icons.duckduckgo.com/*' => Http::response('', 404)]);

    expect(Favicon::url('sem-favicon.com.br'))->toBeNull();
});

it('returns null when the service is unreachable', function () {
    Http::fake(['icons.duckduckgo.com/*' => fn () => throw new ConnectionException('timeout')]);

    expect(Favicon::url('github.com'))->toBeNull();
});

it('asks duckduckgo once per domain', function () {
    Http::fake(['icons.duckduckgo.com/*' => Http::response('', 200)]);

    Favicon::url('github.com');
    Favicon::url('GitHub.com');

    Http::assertSentCount(1);
});

it('rejects empty or malformed domains without a request', function (?string $domain) {
    Http::fake();

    expect(Favicon::url($domain))->toBeNull();

    Http::assertNothingSent();
})->with([null, '', 'localhost', 'evil.com/../x', 'a b.com', 'host.com:8080']);
