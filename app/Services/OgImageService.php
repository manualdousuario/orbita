<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Og\TextFitter;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use RuntimeException;

/**
 * Renders deterministic Open Graph images for posts on demand.
 */
class OgImageService
{
    private const CANVAS_MARGIN = 72;

    /** Right edge that every right-aligned element is anchored to. */
    private const CONTENT_RIGHT = 1128;

    /** Top of the band the title/description block is centred in. */
    private const ZONE_TOP = 168;

    /** Bottom of that band, with and without the author row below it. */
    private const ZONE_BOTTOM_WITH_FOOTER = 440;

    private const ZONE_BOTTOM_WITHOUT_FOOTER = 542;

    private const TITLE_MAX_WIDTH = 940;

    private const DESCRIPTION_MAX_WIDTH = 900;

    private const DESCRIPTION_SIZE = 26.0;

    private const DESCRIPTION_LINE_HEIGHT = 36;

    /** Space between the last title line and the first description line. */
    private const BLOCK_GAP = 26;

    /**
     * Title sizes tried largest first.
     *
     * @var list<float>
     */
    private const TITLE_LADDER = [76.0, 66.0, 58.0, 52.0, 46.0, 42.0];

    private const TITLE_MAX_LINES = 3;

    /** Inter's baseline sits at roughly this fraction of the em below the line box top. */
    private const BASELINE_RATIO = 0.79;

    private const AVATAR_SIZE = 52;

    private const AVATAR_ORIGIN = [72, 506];

    /** Non-breaking abbreviations, so the footer date never depends on the app locale. */
    private const MONTHS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    public function render(
        string $title,
        string $description = '',
        ?string $authorName = null,
        ?string $authorHandle = null,
        ?string $avatarUrl = null,
        ?DateTimeInterface $publishedAt = null,
    ): string {
        // Imagick OpenMP threads thrash php-fpm; one thread is faster anyway.
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_THREAD, 1);

        $title = $this->sanitize($title);
        $description = $this->sanitize($description);
        $authorName = $this->sanitize((string) $authorName);

        $canvas = new Imagick($this->platePath());

        $hasFooter = $authorName !== '';

        $this->text($canvas, $this->font('Medium'), 22, '#c7d2fe', self::CONTENT_RIGHT, 84, $this->domain(), 0.80, Imagick::ALIGN_RIGHT);

        if ($hasFooter) {
            $this->drawFooter($canvas, $authorName, (string) $authorHandle, $avatarUrl, $publishedAt);
        }

        $this->drawContent($canvas, $title, $description, $hasFooter);

        $canvas->setImageFormat('jpeg');
        $canvas->setImageCompressionQuality(88);
        $canvas->setInterlaceScheme(Imagick::INTERLACE_PLANE);
        $canvas->stripImage();

        $bytes = $canvas->getImageBlob();
        $canvas->clear();

        return $bytes;
    }

    /**
     * Fits the title against the size ladder and centres the block in the content zone.
     */
    private function drawContent(Imagick $canvas, string $title, string $description, bool $hasFooter): void
    {
        $titleFont = $this->font('Bold');
        $descriptionFont = $this->font('Regular');

        $titleFitter = new TextFitter($canvas, $titleFont, $title);
        $descriptionFitter = new TextFitter($canvas, $descriptionFont, $description);

        $zoneTop = self::ZONE_TOP;
        $zoneHeight = ($hasFooter ? self::ZONE_BOTTOM_WITH_FOOTER : self::ZONE_BOTTOM_WITHOUT_FOOTER) - $zoneTop;

        [$size, $lineHeight, $titleLines, $descriptionLines, $blockHeight] =
            $this->fitBlock($titleFitter, $descriptionFitter, $zoneHeight);

        $top = $zoneTop + (int) round(($zoneHeight - $blockHeight) / 2);

        foreach ($titleLines as $index => $line) {
            $this->text(
                $canvas, $titleFont, $size, '#ffffff',
                self::CANVAS_MARGIN, $top + $index * $lineHeight + $size * self::BASELINE_RATIO, $line,
            );
        }

        if ($descriptionLines === []) {
            return;
        }

        $descriptionTop = $top + count($titleLines) * $lineHeight + self::BLOCK_GAP;

        foreach ($descriptionLines as $index => $line) {
            $this->text(
                $canvas, $descriptionFont, self::DESCRIPTION_SIZE, '#c7d2fe',
                self::CANVAS_MARGIN,
                $descriptionTop + $index * self::DESCRIPTION_LINE_HEIGHT + self::DESCRIPTION_SIZE * self::BASELINE_RATIO,
                $line, 0.92,
            );
        }
    }

    /**
     * Fits width first, then height, dropping the description rather than the title.
     *
     * @return array{0: float, 1: int, 2: list<string>, 3: list<string>, 4: int}
     */
    private function fitBlock(TextFitter $title, TextFitter $description, int $zoneHeight): array
    {
        $lastRung = count(self::TITLE_LADDER) - 1;
        $fallback = null;

        foreach (self::TITLE_LADDER as $rung => $size) {
            ['lines' => $titleLines, 'truncated' => $truncated] = $title->lines($size, self::TITLE_MAX_WIDTH, self::TITLE_MAX_LINES);

            if ($truncated && $rung !== $lastRung) {
                continue;
            }

            $lineHeight = (int) round($size * 1.16);
            $titleHeight = count($titleLines) * $lineHeight;

            // A title already filling the zone leaves room for one description line at most.
            $descriptionMax = count($titleLines) >= self::TITLE_MAX_LINES ? 1 : 2;
            $descriptionLines = $description->lines(self::DESCRIPTION_SIZE, self::DESCRIPTION_MAX_WIDTH, $descriptionMax)['lines'];

            $blockHeight = $titleHeight + ($descriptionLines === [] ? 0 : self::BLOCK_GAP + count($descriptionLines) * self::DESCRIPTION_LINE_HEIGHT);

            if ($blockHeight <= $zoneHeight) {
                return [$size, $lineHeight, $titleLines, $descriptionLines, $blockHeight];
            }

            if ($descriptionLines !== [] && $titleHeight <= $zoneHeight && $size <= 58.0) {
                return [$size, $lineHeight, $titleLines, [], $titleHeight];
            }

            $fallback = [$size, $lineHeight, $titleLines, [], $titleHeight];
        }

        // Reached only when even the smallest rung overflows, e.g. a zone shrunk by config.
        return $fallback ?? [42.0, 49, $title->lines(42.0, self::TITLE_MAX_WIDTH, self::TITLE_MAX_LINES)['lines'], [], 0];
    }

    private function drawFooter(Imagick $canvas, string $name, string $handle, ?string $avatarUrl, ?DateTimeInterface $publishedAt): void
    {
        $hairline = new ImagickDraw;
        $hairline->setFillColor($this->pixel('#ffffff', 0.14));
        $hairline->rectangle(self::CANVAS_MARGIN, 470, self::CONTENT_RIGHT, 470);
        $canvas->drawImage($hairline);

        [$avatarX, $avatarY] = self::AVATAR_ORIGIN;
        $avatar = $this->avatar($avatarUrl, $name);
        $canvas->compositeImage($avatar, Imagick::COMPOSITE_OVER, $avatarX, $avatarY);
        $avatar->clear();

        $centreX = $avatarX + intdiv(self::AVATAR_SIZE, 2);
        $centreY = $avatarY + intdiv(self::AVATAR_SIZE, 2);

        $ring = new ImagickDraw;
        $ring->setFillColor(new ImagickPixel('none'));
        $ring->setStrokeColor($this->pixel('#a5b4fc', 0.55));
        $ring->setStrokeWidth(2);
        $ring->circle($centreX, $centreY, $centreX, $avatarY);
        $canvas->drawImage($ring);

        $this->text($canvas, $this->font('SemiBold'), 26, '#ffffff', 144, 529, $name, 0.95);

        if ($handle !== '') {
            $this->text($canvas, $this->font('Regular'), 22, '#a5b4fc', 144, 554, '@'.$handle, 0.85);
        }

        if ($publishedAt instanceof DateTimeInterface) {
            $this->text(
                $canvas, $this->font('Medium'), 24, '#c7d2fe',
                self::CONTENT_RIGHT, 541, $this->formatDate($publishedAt), 0.78, Imagick::ALIGN_RIGHT,
            );
        }
    }

    /**
     * The author's photo from our own disk, or a lettered placeholder.
     */
    private function avatar(?string $avatarUrl, string $name): Imagick
    {
        $path = $this->localAvatarPath($avatarUrl);

        if ($path === null) {
            return $this->letteredAvatar($name);
        }

        try {
            $avatar = new Imagick($path);
        } catch (\Throwable) {
            return $this->letteredAvatar($name);
        }

        // Mask at 4x and downsample to keep the circle edge smooth.
        $supersample = self::AVATAR_SIZE * 4;

        $avatar->setImageFormat('png32');
        // An uploaded PNG with transparency would otherwise composite as a black halo.
        $avatar->setImageBackgroundColor(new ImagickPixel('#1f2937'));
        $avatar = $avatar->flattenImages();
        $avatar->cropThumbnailImage($supersample, $supersample);

        $mask = new Imagick;
        $mask->newImage($supersample, $supersample, new ImagickPixel('none'));
        $mask->setImageFormat('png32');

        $circle = new ImagickDraw;
        $circle->setFillColor(new ImagickPixel('white'));
        $circle->circle($supersample / 2, $supersample / 2, $supersample / 2, 0);
        $mask->drawImage($circle);

        $avatar->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        $avatar->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);
        $avatar->resizeImage(self::AVATAR_SIZE, self::AVATAR_SIZE, Imagick::FILTER_LANCZOS, 1);

        $mask->clear();

        return $avatar;
    }

    private function letteredAvatar(string $name): Imagick
    {
        $size = self::AVATAR_SIZE;

        $avatar = new Imagick;
        $avatar->newImage($size, $size, new ImagickPixel('none'));
        $avatar->setImageFormat('png32');

        $disc = new ImagickDraw;
        $disc->setFillColor(new ImagickPixel('#4338ca'));
        $disc->circle($size / 2, $size / 2, $size / 2, 0);
        $avatar->drawImage($disc);

        $initial = mb_strtoupper(mb_substr(trim($name), 0, 1));

        if ($initial !== '') {
            $this->text($avatar, $this->font('SemiBold'), 24, '#e0e7ff', $size / 2, $size / 2 + 24 * 0.35, $initial, 1.0, Imagick::ALIGN_CENTER);
        }

        return $avatar;
    }

    /** Maps a stored avatar_url ("/s/avatars/xyz.jpg") back to a file on the local disk. */
    private function localAvatarPath(?string $avatarUrl): ?string
    {
        $avatarUrl = trim((string) $avatarUrl);

        if ($avatarUrl === '' || ! str_starts_with($avatarUrl, '/s/')) {
            return null;
        }

        $relative = substr($avatarUrl, 3);

        // Defensive: the value is user-adjacent and feeds a filesystem path.
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $disk = Storage::disk('local');

        return $disk->exists($relative) ? $disk->path($relative) : null;
    }

    private function text(
        Imagick $canvas,
        string $fontPath,
        float $size,
        string $color,
        float $x,
        float $y,
        string $text,
        float $opacity = 1.0,
        int $alignment = Imagick::ALIGN_LEFT,
    ): void {
        if ($text === '') {
            return;
        }

        $draw = new ImagickDraw;
        $draw->setFont($fontPath);
        $draw->setFontSize($size);
        $draw->setFillColor($this->pixel($color, $opacity));
        $draw->setTextAlignment($alignment);
        $draw->annotation($x, $y, $text);

        $canvas->drawImage($draw);
    }

    private function pixel(string $color, float $opacity): ImagickPixel
    {
        $pixel = new ImagickPixel($color);
        $pixel->setColorValue(Imagick::COLOR_ALPHA, $opacity);

        return $pixel;
    }

    private function font(string $weight): string
    {
        $path = resource_path('fonts/inter/Inter-'.$weight.'.ttf');

        if (! is_file($path)) {
            throw new RuntimeException("Missing Open Graph font: {$path}");
        }

        return $path;
    }

    private function platePath(): string
    {
        $path = public_path('images/og-plate.jpg');

        if (! is_file($path)) {
            throw new RuntimeException("Missing Open Graph plate: {$path}. Run `php artisan og:bake-plate`.");
        }

        return $path;
    }

    private function domain(): string
    {
        $url = (string) (config('orbita.url') ?: config('app.url'));

        return mb_strtolower(parse_url($url, PHP_URL_HOST) ?: $url);
    }

    private function formatDate(DateTimeInterface $date): string
    {
        return $date->format('j').' '.self::MONTHS[(int) $date->format('n') - 1].' '.$date->format('Y');
    }

    /**
     * Strips characters the monochrome font cannot draw, then collapses whitespace.
     */
    private function sanitize(string $text): string
    {
        $stripped = preg_replace(
            '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE00}-\x{FE0F}\x{200D}]/u',
            '',
            $text,
        );

        return trim(preg_replace('/\s+/u', ' ', $stripped ?? $text));
    }
}
