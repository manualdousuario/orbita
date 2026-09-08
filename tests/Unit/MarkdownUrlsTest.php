<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MarkdownUrls;

it('extracts the three link forms', function () {
    $urls = MarkdownUrls::extract(implode("\n", [
        'Veja [a oferta](https://loja.com/p).',
        'Ou <https://loja.com/q>',
        'Ou https://loja.com/r direto.',
    ]));

    expect($urls)->toEqualCanonicalizing([
        'https://loja.com/p',
        'https://loja.com/q',
        'https://loja.com/r',
    ]);
});

it('drops the sentence punctuation after a bare url', function () {
    expect(MarkdownUrls::extract('Veja https://loja.com/p.'))->toBe(['https://loja.com/p']);
    expect(MarkdownUrls::extract('Veja https://loja.com/p, agora'))->toBe(['https://loja.com/p']);
});

it('ignores links inside fenced and inline code', function () {
    $markdown = implode("\n", [
        'Inline `https://oculto.com/p` fica.',
        '',
        '```',
        'curl https://tambem-oculto.com/q',
        '```',
        '',
        'Mas https://visivel.com/r conta.',
    ]);

    expect(MarkdownUrls::extract($markdown))->toBe(['https://visivel.com/r']);
});

it('returns nothing for text without links', function () {
    expect(MarkdownUrls::extract('Um comentário comum, sem links.'))->toBe([])
        ->and(MarkdownUrls::extract(''))->toBe([]);
});

it('deduplicates repeated urls', function () {
    expect(MarkdownUrls::extract('https://a.com/x e de novo https://a.com/x'))
        ->toBe(['https://a.com/x']);
});

it('reads the host lowercased and without www', function () {
    expect(MarkdownUrls::host('https://WWW.Loja.COM/p?x=1'))->toBe('loja.com')
        ->and(MarkdownUrls::host('https://sub.loja.com/p'))->toBe('sub.loja.com');
});

it('reads the host of a scheme less link', function () {
    expect(MarkdownUrls::host('www.loja.com/p'))->toBe('loja.com');
});

it('returns null for a url without a host', function () {
    expect(MarkdownUrls::host(null))->toBeNull()
        ->and(MarkdownUrls::host(''))->toBeNull()
        ->and(MarkdownUrls::host('   '))->toBeNull();
});

it('collects the unique hosts of a body', function () {
    $hosts = MarkdownUrls::hosts('Veja https://a.com/1 e https://www.a.com/2 e https://b.com/3');

    expect($hosts)->toEqualCanonicalizing(['a.com', 'b.com']);
});

it('preserves the port in the url but not in the host', function () {
    expect(MarkdownUrls::host('https://loja.com:8443/p'))->toBe('loja.com');
});
