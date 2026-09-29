<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class SearchSnippet
{
    private const ACCENTS = [
        'a' => 'aáàâãä', 'e' => 'eéèêë', 'i' => 'iíìîï', 'o' => 'oóòôõö', 'u' => 'uúùûü', 'c' => 'cç', 'n' => 'nñ',
    ];

    public static function make(string $markdown, string $query, int $length = 200): HtmlString
    {
        $plain = html_entity_decode(Markdown::toText($markdown), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $plain));
        $regex = self::regex($query);

        $start = 0;
        if ($regex !== null && preg_match($regex, $text, $m, PREG_OFFSET_CAPTURE)) {
            $hit = mb_strlen(substr($text, 0, $m[0][1]));
            $start = max(0, $hit - intdiv($length, 3));
        }

        $excerpt = mb_substr($text, $start, $length);
        $prefix = $start > 0 ? '… ' : '';
        $suffix = $start + $length < mb_strlen($text) ? ' …' : '';

        $parts = $regex !== null
            ? (preg_split($regex, $excerpt, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$excerpt])
            : [$excerpt];

        $html = '';
        foreach ($parts as $i => $part) {
            $html .= $i % 2 === 1 ? '<mark>'.e($part).'</mark>' : e($part);
        }

        return new HtmlString($prefix.$html.$suffix);
    }

    private static function regex(string $query): ?string
    {
        $words = array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: [],
            fn (string $w): bool => mb_strlen($w) >= 2,
        );

        if ($words === []) {
            return null;
        }

        $alternatives = array_map(function (string $word): string {
            $out = '';
            foreach (mb_str_split(self::fold($word)) as $char) {
                $out .= isset(self::ACCENTS[$char]) ? '['.self::ACCENTS[$char].']' : preg_quote($char, '/');
            }

            return $out;
        }, array_values(array_unique($words)));

        return '/('.implode('|', $alternatives).')/iu';
    }

    private static function fold(string $word): string
    {
        foreach (self::ACCENTS as $base => $variants) {
            $word = (string) preg_replace('/['.$variants.']/u', $base, $word);
        }

        return $word;
    }
}
