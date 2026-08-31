<?php

declare(strict_types=1);

/**
 * Covers SetSecurityHeaders and the Vary: Cookie that SetEdgeCacheHeaders adds.
 */

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('flat security headers are present on an html response', function () {
    $response = get('/');

    $response->assertOk();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

    expect((string) $response->headers->get('Permissions-Policy'))->toContain('camera=()');
});

/**
 * The CSP ships in Report-Only, never enforcement, by default.
 */
it('the csp ships in report only and never as enforcement by default', function () {
    $response = get('/');

    $response->assertHeaderMissing('Content-Security-Policy');

    $csp = (string) $response->headers->get('Content-Security-Policy-Report-Only');

    expect($csp)->not->toBe('');
    expect($csp)->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        // Alpine compiles x-* expressions with the Function constructor, needing unsafe-eval.
        ->toContain("'unsafe-eval'");

    // oEmbed renders provider iframes, so frame-src cannot be 'self' only.
    expect($csp)->toMatch('/frame-src [^;]*https:/');
});

it('enforcement swaps the header when explicitly turned on', function () {
    config(['orbita.security.csp.enforce' => true]);

    $response = get('/');

    $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
    expect((string) $response->headers->get('Content-Security-Policy'))->not->toBe('');
});

/**
 * Cacheable routes must Vary on Cookie.
 */
it('cacheable routes vary on cookie', function () {
    expect(strtolower((string) get('/')->headers->get('Vary')))->toContain('cookie');
});

it('an authenticated response also varies on cookie', function () {
    actingAs(User::factory()->createOne());

    $response = get('/');

    expect(strtolower((string) $response->headers->get('Vary')))->toContain('cookie');
    expect((string) $response->headers->get('Cache-Control'))->toContain('private');
});
