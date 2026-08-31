<?php

declare(strict_types=1);

/**
 * Tests the OG-card text fitter's wrapping and truncation.
 */

namespace Tests\Feature\Og;

use App\Services\Og\TextFitter;
use Imagick;
use ImagickPixel;

/**
 * A 1x1 scratch surface the fitter measures glyphs against. It carries no test
 * state, so one instance is shared instead of being rebuilt per test.
 */
function canvas(): Imagick
{
    static $canvas = null;

    if (! $canvas instanceof Imagick) {
        $canvas = new Imagick;
        $canvas->newImage(1, 1, new ImagickPixel('black'));
    }

    return $canvas;
}

function fitter(string $text, string $weight = 'Bold'): TextFitter
{
    return new TextFitter(canvas(), resource_path('fonts/inter/Inter-'.$weight.'.ttf'), $text);
}

it('a short line is neither wrapped nor truncated', function () {
    $result = fitter('Buracos negros')->lines(76, 940, 3);

    expect($result['lines'])->toBe(['Buracos negros'])
        ->and($result['truncated'])->toBeFalse();
});

it('empty text produces no lines', function () {
    $fitter = fitter('   ');

    expect($fitter->isEmpty())->toBeTrue()
        ->and($fitter->lines(76, 940, 3)['lines'])->toBe([]);
});

it('a long run wraps within the line limit', function () {
    $result = fitter('Como o algoritmo do Órbita calcula o ranking das conversas em tempo real')
        ->lines(66, 940, 3);

    expect(count($result['lines']))->toBeGreaterThan(1)->toBeLessThanOrEqual(3);
    expect($result['truncated'])->toBeFalse();
});

it('overflow is truncated with a single ellipsis glyph on the last line', function () {
    $result = fitter(str_repeat('palavra ', 60))->lines(76, 940, 3);

    expect($result['lines'])->toHaveCount(3)
        ->and($result['truncated'])->toBeTrue();

    expect($result['lines'][2])->toEndWith('…');
    expect($result['lines'][2])->not->toContain('...');

    expect($result['lines'][0])->not->toContain('…');
    expect($result['lines'][1])->not->toContain('…');
});

/**
 * An unbreakable token wider than the card is hard-broken.
 */
it('a single unbreakable token is broken rather than overflowing', function () {
    $result = fitter(str_repeat('a', 300))->lines(76, 940, 3);

    expect($result['lines'])->toHaveCount(3);

    foreach ($result['lines'] as $line) {
        expect(mb_strlen($line))->toBeLessThan(60);
    }
});

/**
 * Truncation never splits a multibyte character.
 */
it('truncation never splits a multibyte character', function () {
    $result = fitter(str_repeat('informação coração ', 30))->lines(76, 940, 3);

    foreach ($result['lines'] as $line) {
        expect($line)->toBe(mb_convert_encoding($line, 'UTF-8', 'UTF-8'))
            ->and(mb_check_encoding($line, 'UTF-8'))->toBeTrue();
    }
});

/**
 * A smaller size has to fit strictly more of the same text on one line.
 */
it('smaller sizes fit more words per line', function () {
    $text = 'Por que o Brasil ainda não tem uma rede social própria de verdade';

    $large = fitter($text)->lines(76, 940, 5)['lines'];
    $small = fitter($text)->lines(42, 940, 5)['lines'];

    expect(count($large))->toBeGreaterThan(count($small));
});

it('a zero line budget yields nothing', function () {
    expect(fitter('Buracos negros')->lines(76, 940, 0)['lines'])->toBe([]);
});
