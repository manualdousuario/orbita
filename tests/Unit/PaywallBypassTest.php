<?php

declare(strict_types=1);

/**
 * Tests the paywall bypass URL wrapper.
 */

namespace Tests\Unit;

use App\Support\PaywallBypass;

beforeEach(function () {
    config([
        'orbita.posts.enable_paywall_bypass' => true,
        'orbita.posts.paywall_bypass_rules' => '',
    ]);
});

// ── baseline behaviour ───────────────────────────────────────────────────────

it('returns original when disabled', function () {
    config([
        'orbita.posts.enable_paywall_bypass' => false,
        'orbita.posts.paywall_bypass_rules' => 'example.com = https://servico-paywall.com',
    ]);

    $url = 'https://example.com/article';
    expect(PaywallBypass::wrap($url))->toBe($url);
});

it('returns original when no rules are configured', function () {
    expect(PaywallBypass::enabled())->toBeFalse();

    $url = 'https://example.com/article';
    expect(PaywallBypass::wrap($url))->toBe($url);
});

it('leaves non absolute and empty urls untouched', function () {
    config(['orbita.posts.paywall_bypass_rules' => 'example.com = https://servico-paywall.com']);

    expect(PaywallBypass::wrap('/relative/path'))->toBe('/relative/path')
        ->and(PaywallBypass::wrap('ftp://example.com/x'))->toBe('ftp://example.com/x')
        ->and(PaywallBypass::wrap(''))->toBe('')
        ->and(PaywallBypass::wrap(null))->toBeNull();
});

// ── per-domain whitelist rules ───────────────────────────────────────────────

it('wraps a whitelisted domain with its proxy', function () {
    config(['orbita.posts.paywall_bypass_rules' => 'folha.uol.com = https://servico-paywall.com']);

    expect(PaywallBypass::wrap('https://folha.uol.com/paywall/materia'))
        ->toBe('https://servico-paywall.com/https://folha.uol.com/paywall/materia');
});

it('domain without a rule is left untouched', function () {
    config(['orbita.posts.paywall_bypass_rules' => 'folha.uol.com = https://servico-paywall.com']);

    $url = 'https://example.com/article';
    expect(PaywallBypass::wrap($url))->toBe($url);
});

it('rule matches subdomains', function () {
    config(['orbita.posts.paywall_bypass_rules' => 'folha.uol.com = https://servico-paywall.com']);

    expect(PaywallBypass::wrap('https://www.folha.uol.com/materia'))
        ->toBe('https://servico-paywall.com/https://www.folha.uol.com/materia');
});

it('most specific rule wins', function () {
    config(['orbita.posts.paywall_bypass_rules' => implode("\n", [
        'uol.com = https://proxy-generico.com',
        'folha.uol.com = https://servico-paywall.com',
    ])]);

    expect(PaywallBypass::wrap('https://folha.uol.com/materia'))
        ->toBe('https://servico-paywall.com/https://folha.uol.com/materia');
    expect(PaywallBypass::wrap('https://www.uol.com/noticia'))
        ->toBe('https://proxy-generico.com/https://www.uol.com/noticia');
});

it('rule does not match lookalike domains', function () {
    config(['orbita.posts.paywall_bypass_rules' => 'folha.uol.com = https://servico-paywall.com']);

    $url = 'https://folha.uol.com.evil.com/phishing';
    expect(PaywallBypass::wrap($url))->toBe($url);
});

it('malformed rule lines are ignored', function () {
    config(['orbita.posts.paywall_bypass_rules' => implode("\n", [
        '# comentário',
        '',
        '   ',
        'sem-separador.com',
        'proxy-sem-esquema.com = servico-paywall.com',
        ' = https://sem-dominio.com',
        'estadao.com.br = https://buraco.com/  ',
    ])]);

    expect(PaywallBypass::wrap('https://www.estadao.com.br/politica'))->toBe(
        'https://buraco.com/https://www.estadao.com.br/politica',
        'valid rule still parses (and the trailing slash is trimmed)',
    );

    $url = 'https://sem-separador.com/x';
    expect(PaywallBypass::wrap($url))->toBe($url, 'malformed lines never produce a matchable rule');
});
