<?php

declare(strict_types=1);

/**
 * Tests the shared log redaction vocabulary.
 */

namespace Tests\Unit;

use App\Support\LogRedactor;

beforeEach(function () {
    config(['orbita.logs.access.redact' => ['signature', 'token', '_token', 'password', 'api_key']]);
});

// ── query string ─────────────────────────────────────────────────────────────

it('replaces sensitive query values and keeps the rest', function () {
    $redacted = LogRedactor::queryString(['signature' => 'deadbeef', 'page' => '2']);

    expect($redacted)->toBe(['signature' => '[redacted]', 'page' => '2']);
});

it('leaves an unrelated query untouched', function () {
    $query = ['page' => '2', 'q' => 'busca'];

    expect(LogRedactor::queryString($query))->toBe($query);
});

it('does not invent keys that were absent', function () {
    expect(LogRedactor::queryString([]))->toBe([]);
});

// ── free text ────────────────────────────────────────────────────────────────

it('redacts secrets in the shapes exception messages use', function () {
    $cases = [
        'failed with token=abc123' => 'failed with token=[redacted]',
        'context: {"api_key":"sk_live_9"}' => 'context: {"api_key":"[redacted]"}',
        "array('password' => 'hunter2')" => "array('password' => '[redacted]')",
        'signature: abcdef' => 'signature: [redacted]',
    ];

    foreach ($cases as $input => $expected) {
        expect(LogRedactor::text($input))->toBe($expected, $input);
    }
});

it('redacts email addresses', function () {
    expect(LogRedactor::text('No query results for user maria@example.com'))
        ->toBe('No query results for user [redacted]');
});

/**
 * Ordinary prose must survive over-redaction.
 */
it('leaves ordinary messages alone', function () {
    $message = 'Undefined array key "slug" in PostService::resolve()';

    expect(LogRedactor::text($message))->toBe($message);
    expect(LogRedactor::text(''))->toBe('');
});

/**
 * A key that merely ends with a sensitive one is not a match.
 */
it('does not match a key that merely ends with a sensitive one', function () {
    expect(LogRedactor::text('csrf_token=abc'))->toBe('csrf_token=abc');
});
