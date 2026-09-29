<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SearchSnippet;

it('strips markdown and marks the hit ignoring case and accents', function () {
    $html = (string) SearchSnippet::make('Veja [o **Firefox**](https://mozilla.org) e a `ação`', 'firefox acao');

    expect($html)->toBe('Veja o <mark>Firefox</mark> e a <mark>ação</mark>');
});

it('centers a long text on the first hit', function () {
    $text = str_repeat('palavra ', 60).'nebulosa no fim';

    $html = (string) SearchSnippet::make($text, 'nebulosa', 120);

    expect($html)->toStartWith('… ')
        ->toContain('<mark>nebulosa</mark>');
});

it('never matches inside escaped entities', function () {
    $html = (string) SearchSnippet::make('a < b', 'lt');

    expect($html)->toBe('a &lt; b');
});
