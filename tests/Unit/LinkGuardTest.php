<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LinkGuard;

beforeEach(function () {
    config([
        'orbita.moderation.link_guard_enabled' => true,
        'orbita.moderation.link_domain_blocklist' => '',
        'orbita.moderation.link_domain_allowlist' => '',
        'app.url' => 'https://orbita.test',
    ]);
});

it('is off by default', function () {
    config(['orbita.moderation.link_guard_enabled' => false]);

    expect(LinkGuard::enabled())->toBeFalse();
});

it('ignores blank and commented lines', function () {
    config(['orbita.moderation.link_domain_blocklist' => implode("\n", [
        '# spam conhecido',
        '',
        '   ',
        '  Spam.com  ',
    ])]);

    expect(LinkGuard::domains('link_domain_blocklist'))->toBe(['spam.com']);
});

it('normalises the wildcard and www prefixes', function () {
    config(['orbita.moderation.link_domain_blocklist' => implode("\n", [
        '*.spam.com',
        'www.outro.com',
    ])]);

    expect(LinkGuard::domains('link_domain_blocklist'))->toBe(['spam.com', 'outro.com']);
});

it('deduplicates entries that normalise to the same domain', function () {
    config(['orbita.moderation.link_domain_blocklist' => "spam.com\n*.spam.com\nwww.spam.com"]);

    expect(LinkGuard::domains('link_domain_blocklist'))->toBe(['spam.com']);
});

it('blocks the domain and its subdomains', function () {
    config(['orbita.moderation.link_domain_blocklist' => 'spam.com']);

    expect(LinkGuard::isBlocked('spam.com'))->toBeTrue()
        ->and(LinkGuard::isBlocked('go.spam.com'))->toBeTrue()
        ->and(LinkGuard::isBlocked('a.b.spam.com'))->toBeTrue();
});

it('does not block a lookalike domain', function () {
    config(['orbita.moderation.link_domain_blocklist' => 'spam.com']);

    expect(LinkGuard::isBlocked('naospam.com'))->toBeFalse()
        ->and(LinkGuard::isBlocked('spam.com.br'))->toBeFalse()
        ->and(LinkGuard::isBlocked('outro.com'))->toBeFalse();
});

it('blocks nothing when the list is empty', function () {
    expect(LinkGuard::isBlocked('spam.com'))->toBeFalse();
});

it('allows the listed domains and the site itself', function () {
    config(['orbita.moderation.link_domain_allowlist' => 'catracalivre.com.br']);

    expect(LinkGuard::isAllowed('catracalivre.com.br'))->toBeTrue()
        ->and(LinkGuard::isAllowed('blog.catracalivre.com.br'))->toBeTrue()
        ->and(LinkGuard::isAllowed('orbita.test'))->toBeTrue()
        ->and(LinkGuard::isAllowed('qualquer.com'))->toBeFalse();
});
