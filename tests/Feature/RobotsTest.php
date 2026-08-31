<?php

declare(strict_types=1);

/**
 * robots.txt served from the admin-editable setting.
 */

namespace Tests\Feature;

use function Pest\Laravel\get;

it('robots is served as plain text with the configured body', function () {
    $response = get('/robots.txt');

    $response->assertOk();

    expect((string) $response->headers->get('Content-Type'))->toStartWith('text/plain');

    $response->assertSee('User-agent: *', false);
    $response->assertSee('Disallow: /admin', false);
});

it('body comes from the admin editable setting', function () {
    config(['orbita.robots.content' => "User-agent: *\nDisallow: /segredo"]);

    $response = get('/robots.txt');

    $response->assertSee('Disallow: /segredo', false);
    $response->assertDontSee('Disallow: /admin', false);
});

it('sitemap directive is appended as an absolute url', function () {
    // The sitemaps protocol requires an absolute URL; the controller appends it.
    config(['orbita.robots.content' => 'User-agent: *']);

    $response = get('/robots.txt');

    $response->assertSee('Sitemap: '.route('sitemap.index'), false);

    expect((string) $response->getContent())->toMatch('#^Sitemap: https?://[^/]+/sitemap\.xml$#m');
});

it('a sitemap line typed into the setting is replaced not duplicated', function () {
    config(['orbita.robots.content' => "User-agent: *\nSitemap: /sitemap.xml\nsitemap: http://antigo.test/sitemap.xml"]);

    $content = (string) get('/robots.txt')->getContent();

    expect(preg_match_all('/^Sitemap:/mi', $content))->toBe(1, 'exactly one Sitemap directive');
    expect($content)->not->toContain('antigo.test');
    expect($content)->not->toContain("Sitemap: /sitemap.xml\n");
});
