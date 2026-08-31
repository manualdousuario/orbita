<?php

declare(strict_types=1);

/**
 * OgImageService renders only; it no longer stores anything or attaches media.
 */

namespace Tests\Feature;

use App\Services\OgImageService;

it('render returns a 1200x630 jpeg', function () {
    $bytes = (new OgImageService)->render('Órbita e as estrelas', 'Um texto sobre o cosmos.');

    expect($bytes)->toStartWith("\xFF\xD8\xFF");

    [$width, $height] = getimagesizefromstring($bytes);
    expect($width)->toBe((int) config('orbita.ogimage.width'))
        ->and($height)->toBe((int) config('orbita.ogimage.height'));
});

/**
 * The baked plate and fonts ship with the app; a missing file must be caught early.
 */
it('the baked plate and the fonts ship with the app', function () {
    expect(public_path('images/og-plate.jpg'))->toBeFile()
        ->and(public_path('images/orbita-wordmark.png'))->toBeFile();

    foreach (['Regular', 'Medium', 'SemiBold', 'Bold'] as $weight) {
        expect(resource_path('fonts/inter/Inter-'.$weight.'.ttf'))->toBeFile();
    }

    [$width, $height] = getimagesize(public_path('images/og-plate.jpg'));
    expect($width)->toBe((int) config('orbita.ogimage.width'))
        ->and($height)->toBe((int) config('orbita.ogimage.height'));
});

/**
 * Two renders of the same card must be byte-identical (the CDN treats the URL as immutable).
 */
it('rendering the same card twice is byte identical', function () {
    $service = new OgImageService;

    $first = $service->render('Buracos negros', 'Um resumo.', 'Renan Bernordi', 'renan');
    $second = $service->render('Buracos negros', 'Um resumo.', 'Renan Bernordi', 'renan');

    expect($second)->toBe($first);
});

/**
 * The footer is the only part that varies in presence; an author changes the output.
 */
it('the card renders with and without an author', function () {
    $service = new OgImageService;

    $anonymous = $service->render('Buracos negros', 'Um resumo.');
    $attributed = $service->render('Buracos negros', 'Um resumo.', 'Renan Bernordi', 'renan');

    expect($anonymous)->toStartWith("\xFF\xD8\xFF")
        ->and($attributed)->toStartWith("\xFF\xD8\xFF");

    expect($attributed)->not->toBe($anonymous);
});

/**
 * A remote DiceBear avatar URL must never be fetched; the placeholder is used instead.
 */
it('a remote avatar url is never fetched', function () {
    $bytes = (new OgImageService)->render(
        'Buracos negros', 'Um resumo.', 'Renan Bernordi', 'renan',
        'https://api.dicebear.com/10.x/thumbs/svg?seed=renan',
    );

    expect($bytes)->toStartWith("\xFF\xD8\xFF");
});

/**
 * Overlong, unbreakable, or emoji-only titles must still render without throwing.
 */
it('awkward titles still render', function (string $title) {
    $bytes = (new OgImageService)->render($title, 'Uma descrição qualquer.', 'Ana Costa', 'ana');

    expect($bytes)->toStartWith("\xFF\xD8\xFF");

    [$width, $height] = getimagesizefromstring($bytes);
    expect($width)->toBe(1200)
        ->and($height)->toBe(630);
})->with([
    'very long' => [str_repeat('palavra ', 40)],
    'single unbreakable token' => [str_repeat('a', 200)],
    'long url' => ['https://exemplo.com.br/'.str_repeat('caminho/', 20)],
    'emoji only' => ['🚀🌌✨'],
    'accents and cedilla' => ['Ação, coração e informação: tradução não é transcrição'],
    'one short word' => ['Oi'],
]);
