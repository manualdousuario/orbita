<?php

declare(strict_types=1);

namespace App\Pipeline\Image;

use App\Support\Exif;
use Intervention\Image\ImageManager;

/**
 * Records dimensions, animation flag and EXIF, passing the original bytes through.
 */
final class ProcessImageStage
{
    public function __construct(
        private ImageManager $imageManager,
    ) {}

    public function __invoke(ImageUploadContext $ctx): ImageUploadContext
    {
        if ($ctx->result !== null) {
            return $ctx;
        }

        // The one decode of the upload: dimensions, animation flag and EXIF all come from it.
        $image = $this->imageManager->decodePath($ctx->file['tmp_name']);
        $ctx->imageWidth = $image->width();
        $ctx->imageHeight = $image->height();
        $ctx->isAnimated = $image->isAnimated();

        try {
            $exif = $image->exif();
            if (! empty($exif)) {
                // Strip raw binary EXIF tags that json_encode() would reject.
                $ctx->exif = Exif::sanitize(is_array($exif) ? $exif : iterator_to_array($exif));
            }
        } catch (\Throwable) {
            // Not all formats carry EXIF; absence is not an error.
        }

        $bytes = file_get_contents($ctx->file['tmp_name']);
        if ($bytes === false) {
            $ctx->result = ['success' => false, 'error' => 'Could not read uploaded file'];

            return $ctx;
        }

        $ctx->processedImageBytes = $bytes;

        // Hash of the bytes about to be stored; media lookups resolve by it.
        $ctx->fileHash = hash('sha256', $bytes);

        return $ctx;
    }
}
