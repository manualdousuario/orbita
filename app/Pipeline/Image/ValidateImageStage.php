<?php

declare(strict_types=1);

namespace App\Pipeline\Image;

use App\Support\ImageFormats;
use League\MimeTypeDetection\FinfoMimeTypeDetector;

/**
 * Validates uploaded image size and MIME, recording the detected format.
 */
final class ValidateImageStage
{
    public function __construct(
        private FinfoMimeTypeDetector $mimeDetector,
        private int $maxSize,
        private int $maxMegapixels = 30,
    ) {}

    public function __invoke(ImageUploadContext $ctx): ImageUploadContext
    {
        $file = $ctx->file;

        if (! isset($file['tmp_name']) || ! file_exists($file['tmp_name'])) {
            $ctx->result = ['success' => false, 'error' => 'No file uploaded'];

            return $ctx;
        }

        if (! is_readable($file['tmp_name'])) {
            $ctx->result = ['success' => false, 'error' => 'File not readable: '.$file['tmp_name']];

            return $ctx;
        }

        if (($file['size'] ?? 0) > $this->maxSize) {
            $maxMB = $this->maxSize / 1024 / 1024;
            $ctx->result = ['success' => false, 'error' => "File size exceeds {$maxMB}MB limit"];

            return $ctx;
        }

        $mimeType = $this->mimeDetector->detectMimeTypeFromFile($file['tmp_name']);
        if (! ImageFormats::isAllowed($mimeType)) {
            $ctx->result = ['success' => false, 'error' => 'Invalid image format. Allowed: '.ImageFormats::label()];

            return $ctx;
        }

        $dimensions = @getimagesize($file['tmp_name']);

        if ($dimensions === false) {
            $ctx->result = ['success' => false, 'error' => 'Invalid image format. Allowed: '.ImageFormats::label()];

            return $ctx;
        }

        [$width, $height] = $dimensions;
        $megapixels = ($width * $height) / 1_000_000;

        if ($megapixels > $this->maxMegapixels) {
            $ctx->result = [
                'success' => false,
                'error' => "Image is too large: {$width}x{$height}px exceeds the {$this->maxMegapixels}MP limit",
            ];

            return $ctx;
        }

        $ctx->mimeType = $mimeType;
        $ctx->extension = ImageFormats::extensionFor($mimeType);

        return $ctx;
    }
}
