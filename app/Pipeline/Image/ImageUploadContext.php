<?php

declare(strict_types=1);

namespace App\Pipeline\Image;

/**
 * Mutable payload that flows through the image upload pipeline.
 */
final class ImageUploadContext
{
    /** When set, remaining stages are skipped (e.g. deduplication hit). */
    public ?array $result = null;

    public string $fileHash = '';

    /** Detected MIME of the uploaded file, e.g. image/png. Set by ValidateImageStage. */
    public string $mimeType = '';

    /** Extension matching $mimeType, e.g. png. Set by ValidateImageStage. */
    public string $extension = '';

    public int $imageWidth = 0;

    public int $imageHeight = 0;

    /**
     * True for multi-frame sources (animated GIF/WebP); never format-convert these.
     */
    public bool $isAnimated = false;

    /** EXIF read once, while the image is already decoded. @var array<string, mixed> */
    public array $exif = [];

    /** The bytes that get stored. Images are kept in their ORIGINAL format and dimensions. */
    public string $processedImageBytes = '';

    public string $tempPath = '';

    public ?int $mediaId = null;

    public string $finalFilename = '';

    public string $finalPath = '';

    public string $dateBasedPath = '';

    /**
     * @param  array<string, mixed>  $file  $_FILES-style array
     */
    public function __construct(
        public readonly array $file,
        public readonly string $directory,
        public readonly ?int $userId,
    ) {}
}
