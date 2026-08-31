<?php

declare(strict_types=1);

/**
 * Tests referral and UTM parameter stripping in URLs and markdown.
 */

namespace Tests\Unit;

use App\Support\ReferralLinks;

beforeEach(function () {
    config([
        'orbita.moderation.strip_referral_params' => true,
        'orbita.moderation.forbidden_url_params' => implode("\n", ['ref', 'referral', 'referral-code', 'utm_*']),
    ]);
});

// ── baseline behaviour ───────────────────────────────────────────────────────

it('returns original when disabled', function () {
    config(['orbita.moderation.strip_referral_params' => false]);

    $url = 'https://loja.com/p?referral=abc';
    expect(ReferralLinks::clean($url))->toBe($url)
        ->and(ReferralLinks::cleanText($url)['content'])->toBe($url);
});

it('returns original when no params are configured', function () {
    config(['orbita.moderation.forbidden_url_params' => '']);

    expect(ReferralLinks::enabled())->toBeFalse();

    $url = 'https://loja.com/p?referral=abc';
    expect(ReferralLinks::clean($url))->toBe($url);
});

it('leaves empty and query less urls untouched', function () {
    expect(ReferralLinks::clean(null))->toBeNull()
        ->and(ReferralLinks::clean(''))->toBe('')
        ->and(ReferralLinks::clean('https://loja.com/p'))->toBe('https://loja.com/p')
        ->and(ReferralLinks::clean('https://loja.com/p?cor=azul'))->toBe('https://loja.com/p?cor=azul');
});

// ── single URL cleanup ───────────────────────────────────────────────────────

it('strips a forbidden param and reports it', function () {
    $result = ReferralLinks::cleanLink('https://loja.com/p?referral=abc');

    expect($result['url'])->toBe('https://loja.com/p')
        ->and($result['stripped'])->toBe(['referral']);
});

it('keeps the legitimate params', function () {
    expect(ReferralLinks::clean('https://loja.com/p?cor=azul&ref=abc&tam=42'))
        ->toBe('https://loja.com/p?cor=azul&tam=42');
});

it('wildcard matches by prefix', function () {
    expect(ReferralLinks::clean('https://loja.com/p?utm_source=x&id=9&utm_medium=y'))
        ->toBe('https://loja.com/p?id=9');
});

it('param names are case insensitive', function () {
    expect(ReferralLinks::clean('https://loja.com/p?Referral=ABC'))->toBe('https://loja.com/p');
});

it('preserves port credentials and fragment', function () {
    expect(ReferralLinks::clean('https://loja.com:8443/p?ref=abc&cor=azul#secao'))
        ->toBe('https://loja.com:8443/p?cor=azul#secao');
});

it('handles a www url without a scheme', function () {
    expect(ReferralLinks::clean('www.loja.com/p?ref=abc&cor=azul'))->toBe('www.loja.com/p?cor=azul');
});

it('a lookalike param name is not stripped', function () {
    $url = 'https://loja.com/p?referral_policy=1&referee=2';
    expect(ReferralLinks::clean($url))->toBe($url);
});

// ── markdown bodies ──────────────────────────────────────────────────────────

it('cleans all three link forms', function () {
    $result = ReferralLinks::cleanText(implode("\n", [
        'Veja [a oferta](https://loja.com/p?referral=abc).',
        'Ou <https://loja.com/q?ref=xyz>',
        'Ou https://loja.com/r?utm_source=zap direto.',
    ]));

    expect($result['content'])->toContain('[a oferta](https://loja.com/p)')
        ->toContain('<https://loja.com/q>')
        ->toContain('https://loja.com/r direto');

    expect($result['stripped'])->toEqualCanonicalizing(['referral', 'ref', 'utm_source']);
});

it('keeps the sentence punctuation after a bare url', function () {
    $result = ReferralLinks::cleanText('Veja https://loja.com/p?ref=abc.');

    expect($result['content'])->toBe('Veja https://loja.com/p.');
});

it('links inside code are left untouched', function () {
    $markdown = implode("\n", [
        'Inline `https://loja.com/p?ref=abc` fica.',
        '',
        '```',
        'curl https://loja.com/q?referral=xyz',
        '```',
    ]);

    $result = ReferralLinks::cleanText($markdown);

    expect($result['content'])->toBe($markdown)
        ->and($result['stripped'])->toBe([]);
});

it('text without links is untouched', function () {
    $result = ReferralLinks::cleanText('Um comentário comum, sem links.');

    expect($result['content'])->toBe('Um comentário comum, sem links.')
        ->and($result['stripped'])->toBe([]);
});

it('malformed param lines are ignored', function () {
    config(['orbita.moderation.forbidden_url_params' => implode("\n", [
        '# comentário',
        '',
        '   ',
        '  referral  ',
        '*',
    ])]);

    expect(ReferralLinks::params())->toBe(['referral', '*']);
    expect(ReferralLinks::clean('https://loja.com/p?referral=abc'))->toBe('https://loja.com/p');

    $url = 'https://loja.com/p?cor=azul';
    expect(ReferralLinks::clean($url))->toBe($url, 'a lone "*" never matches everything');
});
