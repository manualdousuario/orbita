<?php

namespace App\Support;

/**
 * Footer quotes, one per line in config('orbita.quotes') as "TEXTO | AUTOR".
 */
class Quotes
{
    /** @return list<array{text: string, author: string}> */
    public static function all(): array
    {
        $out = [];

        foreach (preg_split('/\R/', (string) config('orbita.quotes', '')) as $line) {
            [$text, $author] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');

            if ($text !== '') {
                $out[] = ['text' => $text, 'author' => $author];
            }
        }

        return $out;
    }

    /** @return array{text: string, author: string}|null */
    public static function random(): ?array
    {
        $quotes = self::all();

        return $quotes === [] ? null : $quotes[array_rand($quotes)];
    }
}
