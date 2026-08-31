<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Imagick;
use ImagickDraw;
use ImagickKernel;
use ImagickPixel;

/**
 * Bakes the OG image background plate and wordmark assets.
 */
class BakeOgPlateCommand extends Command
{
    protected $signature = 'og:bake-plate {--force : Overwrite the assets without confirming}';

    protected $description = 'Bake the static background plate and the Órbita wordmark used by the post OG image';

    private const WORDMARK_CROP = [240, 245, 880, 240];

    private const PLATE_CROP = [813, 475, 467, 245];

    public function handle(): int
    {
        if (! extension_loaded('imagick')) {
            $this->error('ext-imagick is required to bake the plate.');

            return self::FAILURE;
        }

        $source = public_path('images/ogimage.jpg');

        if (! is_file($source)) {
            $this->error("Source artwork not found: {$source}");

            return self::FAILURE;
        }

        $wordmarkPath = public_path('images/orbita-wordmark.png');
        $platePath = public_path('images/og-plate.jpg');

        if (! $this->option('force') && (is_file($wordmarkPath) || is_file($platePath))) {
            if (! $this->confirm('Overwrite the existing baked assets?', true)) {
                return self::SUCCESS;
            }
        }

        $this->components->task('Extracting the wordmark', function () use ($source, $wordmarkPath): void {
            $this->bakeWordmark($source, $wordmarkPath);
        });

        $this->components->task('Baking the background plate', function () use ($source, $wordmarkPath, $platePath): void {
            $this->bakePlate($source, $wordmarkPath, $platePath);
        });

        foreach ([$wordmarkPath, $platePath] as $path) {
            $size = (int) round(filesize($path) / 1024);
            [$w, $h] = getimagesize($path);
            $this->line(sprintf('  <fg=gray>%s</> %dx%d, %d KB', basename($path), $w, $h, $size));
        }

        $this->newLine();
        $this->components->info('Done. Commit both files.');

        return self::SUCCESS;
    }

    private function bakeWordmark(string $source, string $destination): void
    {
        [$x, $y, $width, $height] = self::WORDMARK_CROP;

        $mask = new Imagick($source);
        $mask->cropImage($width, $height, $x, $y);
        $mask->setImagePage(0, 0, 0, 0);
        $mask->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        $mask->levelImage(0.62 * 65535, 1.0, 0.90 * 65535);
        $mask->morphology(Imagick::MORPHOLOGY_OPEN, 1, ImagickKernel::fromBuiltIn(Imagick::KERNEL_DISK, '2'));

        $wordmark = new Imagick;
        $wordmark->newImage($width, $height, new ImagickPixel('white'));
        $wordmark->setImageFormat('png32');
        $wordmark->compositeImage($mask, Imagick::COMPOSITE_COPYALPHA, 0, 0);
        $wordmark->trimImage(0);
        $wordmark->setImagePage(0, 0, 0, 0);
        $wordmark->writeImage($destination);

        $mask->clear();
        $wordmark->clear();
    }

    private function bakePlate(string $source, string $wordmarkPath, string $destination): void
    {
        $width = (int) config('orbita.ogimage.width');
        $height = (int) config('orbita.ogimage.height');

        [$cx, $cy, $cw, $ch] = self::PLATE_CROP;

        $plate = new Imagick($source);
        $plate->cropImage($cw, $ch, $cx, $cy);
        $plate->setImagePage(0, 0, 0, 0);
        $plate->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
        $plate->modulateImage(78, 108, 100);
        $plate->setImageFormat('png32');

        $left = new Imagick;
        $left->newPseudoImage($height, $width, 'gradient:rgba(3,7,18,0.94)-rgba(3,7,18,0.10)');
        $left->rotateImage(new ImagickPixel('none'), -90);
        $plate->compositeImage($left, Imagick::COMPOSITE_OVER, 0, 0);

        $bottomHeight = (int) round($height * 0.476);
        $bottom = new Imagick;
        $bottom->newPseudoImage($width, $bottomHeight, 'gradient:rgba(3,7,18,0.00)-rgba(3,7,18,0.80)');
        $plate->compositeImage($bottom, Imagick::COMPOSITE_OVER, 0, $height - $bottomHeight);

        $plate->compositeImage($this->orbitGlyph(560, '#818cf8', 0.16), Imagick::COMPOSITE_OVER, 905, 205);

        $wordmark = new Imagick($wordmarkPath);
        $wordmark->resizeImage(153, 44, Imagick::FILTER_LANCZOS, 1);
        $plate->compositeImage($wordmark, Imagick::COMPOSITE_OVER, 72, 54);

        $rule = new ImagickDraw;
        $rule->setFillColor(new ImagickPixel('#818cf8'));
        $rule->rectangle(72, 132, 132, 137);
        $plate->drawImage($rule);

        $plate->setImageFormat('jpeg');
        $plate->setImageCompressionQuality(92);
        $plate->stripImage();
        $plate->writeImage($destination);

        foreach ([$left, $bottom, $wordmark, $plate] as $image) {
            $image->clear();
        }
    }

    private function orbitGlyph(int $size, string $color, float $opacity): Imagick
    {
        $scale = $size / 450.0;

        $glyph = new Imagick;
        $glyph->newImage($size, (int) round(470 * $scale), new ImagickPixel('none'));
        $glyph->setImageFormat('png32');

        $ring = new ImagickDraw;
        $ring->setFillColor(new ImagickPixel('none'));
        $ring->setStrokeColor(new ImagickPixel($color));
        $ring->setStrokeWidth(49.39 * $scale);
        $ring->circle(225 * $scale, 244.76 * $scale, (225 + 200.645) * $scale, 244.76 * $scale);
        $glyph->drawImage($ring);

        $discs = new ImagickDraw;
        $discs->setFillColor(new ImagickPixel($color));
        $discs->setStrokeWidth(0);
        $discs->circle(228.09 * $scale, 247.85 * $scale, (228.09 + 117.3) * $scale, 247.85 * $scale);
        $discs->circle(302.17 * $scale, 62.63 * $scale, (302.17 + 61.74) * $scale, 62.63 * $scale);
        $glyph->drawImage($discs);

        $glyph->evaluateImage(Imagick::EVALUATE_MULTIPLY, $opacity, Imagick::CHANNEL_ALPHA);

        return $glyph;
    }
}
