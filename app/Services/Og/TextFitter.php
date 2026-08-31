<?php

declare(strict_types=1);

namespace App\Services\Og;

use Imagick;
use ImagickDraw;

/**
 * Measures text with Imagick and wraps it into lines with an ellipsis.
 */
final class TextFitter
{
    /** Every width is measured at this size and scaled from it. */
    private const REFERENCE_SIZE = 100.0;

    /** Shaves the wrap limit to absorb the linear-scaling error described above. */
    private const SAFETY_MARGIN = 0.99;

    /** U+2026. A single glyph -- three periods would measure and hyphenate differently. */
    private const ELLIPSIS = '…';

    /** @var list<string> */
    private array $words;

    /** @var array<string, float> Reference-size widths, memoised across ladder rungs. */
    private array $widths = [];

    private readonly ImagickDraw $draw;

    private readonly float $spaceWidth;

    public function __construct(
        private readonly Imagick $canvas,
        string $fontPath,
        string $text,
    ) {
        $this->draw = new ImagickDraw;
        $this->draw->setFont($fontPath);
        $this->draw->setFontSize(self::REFERENCE_SIZE);

        $text = trim($text);
        $this->words = $text === '' ? [] : preg_split('/\s+/u', $text);

        $this->spaceWidth = $this->width('a b') - $this->width('ab');
    }

    public function isEmpty(): bool
    {
        return $this->words === [];
    }

    /**
     * Wraps the text into at most $maxLines lines, truncating with an ellipsis.
     */
    public function lines(float $size, float $maxWidth, int $maxLines): array
    {
        if ($this->isEmpty() || $maxLines < 1) {
            return ['lines' => [], 'truncated' => false];
        }

        $scale = $size / self::REFERENCE_SIZE;
        $limit = $maxWidth / $scale * self::SAFETY_MARGIN;

        $words = $this->splitOverlongWords($this->words, $limit);

        $lines = [];
        $current = '';
        $currentWidth = 0.0;
        $truncated = false;

        foreach ($words as $word) {
            $wordWidth = $this->width($word);
            $addition = $current === '' ? $wordWidth : $this->spaceWidth + $wordWidth;

            if ($current === '' || $currentWidth + $addition <= $limit) {
                $current = $current === '' ? $word : $current.' '.$word;
                $currentWidth += $addition;

                continue;
            }

            $lines[] = [$current, $currentWidth];

            if (count($lines) === $maxLines) {
                $truncated = true;
                break;
            }

            $current = $word;
            $currentWidth = $wordWidth;
        }

        if (! $truncated && $current !== '') {
            $lines[] = [$current, $currentWidth];
        }

        if ($truncated) {
            $lines[] = $this->ellipsise(array_pop($lines), $limit);
        }

        return ['lines' => array_column($lines, 0), 'truncated' => $truncated];
    }

    private function ellipsise(array $line, float $limit): array
    {
        [$text, $width] = $line;
        $ellipsisWidth = $this->width(self::ELLIPSIS);
        $parts = explode(' ', $text);

        while (count($parts) > 1 && $width + $ellipsisWidth > $limit) {
            $dropped = array_pop($parts);
            $width -= $this->width($dropped) + $this->spaceWidth;
        }

        if (count($parts) === 1 && $width + $ellipsisWidth > $limit) {
            $only = $parts[0];

            while (mb_strlen($only) > 1) {
                $only = mb_substr($only, 0, -1);
                $width = $this->width($only);

                if ($width + $ellipsisWidth <= $limit) {
                    break;
                }
            }

            $parts = [$only];
        }

        return [implode(' ', $parts).self::ELLIPSIS, $width + $ellipsisWidth];
    }

    private function splitOverlongWords(array $words, float $limit): array
    {
        $result = [];

        foreach ($words as $word) {
            if ($this->width($word) <= $limit) {
                $result[] = $word;

                continue;
            }

            $chunk = '';

            foreach (mb_str_split($word) as $character) {
                if ($chunk !== '' && $this->width($chunk.$character) > $limit) {
                    $result[] = $chunk;
                    $chunk = '';
                }

                $chunk .= $character;
            }

            if ($chunk !== '') {
                $result[] = $chunk;
            }
        }

        return $result;
    }

    private function width(string $text): float
    {
        return $this->widths[$text] ??= (float) $this->canvas->queryFontMetrics($this->draw, $text)['textWidth'];
    }
}
