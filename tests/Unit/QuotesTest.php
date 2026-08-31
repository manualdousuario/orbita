<?php

declare(strict_types=1);

/**
 * Tests the "TEXTO | AUTOR" footer quotes parser.
 */

namespace Tests\Unit;

use App\Support\Quotes;

it('parses text, optional author and skips blank lines', function () {
    config(['orbita.quotes' => "  Frase um | Autor um \n\n Só a frase \n | sem texto \nA | B | C"]);

    expect(Quotes::all())->toBe([
        ['text' => 'Frase um', 'author' => 'Autor um'],
        ['text' => 'Só a frase', 'author' => ''],
        ['text' => 'A', 'author' => 'B | C'],
    ]);
});

it('returns null when empty', function () {
    config(['orbita.quotes' => '']);

    expect(Quotes::random())->toBeNull();
});
