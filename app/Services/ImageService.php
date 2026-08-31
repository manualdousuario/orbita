<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidImageException;
use App\Models\Media;
use App\Pipeline\Image\ImagePipeline;
use App\Pipeline\Image\ImageUploadContext;
use App\Pipeline\Image\ProcessImageStage;
use App\Pipeline\Image\StoreImageStage;
use App\Pipeline\Image\ValidateImageStage;
use App\Support\HashId;
use App\Support\ImageDriver;
use App\Support\ImageFormats;
use App\Support\OutboundHttp;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use League\MimeTypeDetection\FinfoMimeTypeDetector;

/**
 * Handles image upload, processing, avatar cropping, and deletion via a pipeline.
 */
class ImageService
{
    /** Default Laravel disk that wraps Flysystem. */
    private const DISK = 'local';

    /** Media-type base directories (relative to the storage disk). */
    private const BASE_POSTS = 'posts';

    private const BASE_AVATARS = 'avatars';

    private const BASE_OGIMAGES = 'ogimages';

    private Filesystem $disk;

    private int $maxWidth;

    private int $maxSize;

    private int $maxMegapixels;

    private FinfoMimeTypeDetector $mimeDetector;

    private ImageManager $imageManager;

    public function __construct()
    {
        $this->disk = Storage::disk(self::DISK);
        $this->maxWidth = (int) config('orbita.posts.image_max_width', 1200);
        $this->maxSize = (int) config('orbita.posts.image_max_size', 10485760);
        $this->maxMegapixels = (int) config('orbita.images.max_megapixels', 30);
        $this->mimeDetector = new FinfoMimeTypeDetector;
        // Driver is always vips with a GD fallback; see App\Support\ImageDriver.
        $this->imageManager = new ImageManager(ImageDriver::make(), strip: true);
    }

    private function getDateBasedPath(string $prefix): string
    {
        $date = new \DateTime;
        if ($prefix === 'posts') {
            return sprintf(
                '%s/%s/%s/%s',
                self::BASE_POSTS,
                $date->format('Y'),
                $date->format('m'),
                $date->format('d'),
            );
        } elseif ($prefix === 'avatars') {
            return self::BASE_AVATARS;
        } elseif ($prefix === 'ogimages') {
            return self::BASE_OGIMAGES;
        } else {
            return $prefix;
        }
    }

    /**
     * Uploads an image through the validation/process/store pipeline.
     *
     * @param  array<string, mixed>  $file  $_FILES-style array
     * @return array<string, mixed>
     */
    public function uploadImage(array $file, string $directory = 'posts', ?int $userId = null, bool $skipUploadCheck = false): array
    {
        // skipUploadCheck bypasses the upload-existence check for CLI/migration use.
        if (! $skipUploadCheck && isset($file['tmp_name']) && ! file_exists($file['tmp_name'])) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }

        $ctx = new ImageUploadContext($file, $directory, $userId);

        try {
            $pipeline = (new ImagePipeline)
                ->pipe(new ValidateImageStage($this->mimeDetector, $this->maxSize, $this->maxMegapixels))
                ->pipe(new ProcessImageStage($this->imageManager))
                ->pipe(new StoreImageStage($this->disk));

            $ctx = $pipeline->process($ctx);
        } catch (\Exception $e) {
            if ($ctx->tempPath !== '' && $this->disk->exists($ctx->tempPath)) {
                $this->disk->delete($ctx->tempPath);
            }
            Log::error('Failed to upload image', [
                'error' => $e->getMessage(),
                'filename' => $file['name'] ?? 'unknown',
                'user_id' => $userId,
            ]);

            return ['success' => false, 'error' => 'Failed to upload image: '.$e->getMessage()];
        }

        $result = $ctx->result ?? ['success' => false, 'error' => 'Unknown pipeline error'];

        if ($result['success'] ?? false) {
            Log::info('Image uploaded successfully', [
                'media_id' => $result['media_id'] ?? null,
                'filename' => $result['filename'] ?? null,
                'user_id' => $userId,
            ]);
        } else {
            Log::warning('Image upload failed', [
                'error' => $result['error'] ?? 'unknown',
                'filename' => $file['name'] ?? 'unknown',
                'user_id' => $userId,
            ]);
        }

        return $result;
    }

    /**
     * Validates the file exists, is readable, within size, and an allowed format.
     *
     * @param  array<string, mixed>  $file
     * @return array{valid: bool, error?: string}
     */
    private function validateImage(array $file): array
    {
        if (! isset($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'No file uploaded'];
        }

        if (! file_exists($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'File not accessible: '.$file['tmp_name']];
        }

        if (! is_readable($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'File not readable: '.$file['tmp_name']];
        }

        if (($file['size'] ?? 0) > $this->maxSize) {
            $maxMB = $this->maxSize / 1024 / 1024;

            return ['valid' => false, 'error' => "File size exceeds {$maxMB}MB limit"];
        }

        // Same allowlist as the upload pipeline: App\Support\ImageFormats is the single source.
        $mimeType = $this->mimeDetector->detectMimeTypeFromFile($file['tmp_name']);

        if (! ImageFormats::isAllowed($mimeType)) {
            return ['valid' => false, 'error' => 'Invalid image format. Allowed: '.ImageFormats::label()];
        }

        return ['valid' => true];
    }

    public function deleteImage(string $path, ?int $mediaId = null, ?int $deletedBy = null): bool
    {
        try {
            $path = ltrim($path, '/');

            if ($this->disk->exists($path)) {
                $pathInfo = pathinfo($path);
                $directory = $pathInfo['dirname'];
                $filename = $pathInfo['basename'];

                $newFilename = 'deleted_'.$filename;
                $newPath = $directory.'/'.$newFilename;

                // A rename, not a read-then-write-then-delete of the whole file.
                $this->disk->move($path, $newPath);

                if ($mediaId && $deletedBy) {
                    $media = Media::find($mediaId);
                    if ($media !== null) {
                        // Mirror the legacy softDelete(): set deleted_by + status, then soft-delete.
                        $media->update([
                            'deleted_by' => $deletedBy,
                            'status' => 'deleted',
                            'path' => $newPath,
                        ]);
                        $media->delete();
                    }
                }

                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function deleteAvatarFile(?string $avatarUrl, ?int $deletedBy = null): bool
    {
        $path = ltrim((string) $avatarUrl, '/');
        $path = str_starts_with($path, 's/') ? substr($path, 2) : $path;

        if ($path === '') {
            return false;
        }

        $media = Media::where('path', $path)->first();

        return $this->deleteImage($path, $media?->id, $deletedBy);
    }

    /**
     * Fetches and stores a remote avatar, returning null when none is offered.
     */
    public function downloadAvatarFromUrl(string $url, int $userId): ?string
    {
        if (! preg_match('~^https?://~i', $url)) {
            return null;
        }

        try {
            $response = OutboundHttp::send(fn (PendingRequest $http) => $http->timeout(5)->get($url));

            if (! $response->successful()) {
                return null;
            }

            $imageData = (string) $response->body();
            if ($imageData === '') {
                return null;
            }

            $mimeType = $this->mimeDetector->detectMimeTypeFromBuffer($imageData);
            if ($mimeType === null || ! str_starts_with($mimeType, 'image/')) {
                return null;
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'gravatar');
            file_put_contents($tempFile, $imageData);

            try {
                // `test: true` wraps a plain temp file rather than an HTTP upload.
                $file = new UploadedFile($tempFile, basename($url), $mimeType, null, true);

                return $this->uploadAvatar($file, $userId);
            } finally {
                if (is_file($tempFile)) {
                    unlink($tempFile);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to download Gravatar', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return null;
        }
    }

    /**
     * Uploads and crops a 300x300 avatar from a PSR-7 or Laravel UploadedFile.
     */
    public function uploadAvatar($uploadedFile, int $userId): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'avatar');

        if (method_exists($uploadedFile, 'getStream')) {
            // PSR-7 style upload.
            file_put_contents($tempFile, $uploadedFile->getStream()->getContents());
            $file = [
                'name' => $uploadedFile->getClientFilename(),
                'tmp_name' => $tempFile,
                'size' => $uploadedFile->getSize(),
                'type' => $uploadedFile->getClientMediaType(),
            ];
        } else {
            // Laravel/Symfony UploadedFile style.
            file_put_contents($tempFile, file_get_contents($uploadedFile->getPathname()));
            $file = [
                'name' => $uploadedFile->getClientOriginalName(),
                'tmp_name' => $tempFile,
                'size' => $uploadedFile->getSize(),
                'type' => $uploadedFile->getMimeType(),
            ];
        }

        // Scratch file must be cleaned up whether or not the store throws.
        try {
            return $this->storeAvatar($file, $tempFile, $userId);
        } finally {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /** @param  array<string, mixed>  $file */
    private function storeAvatar(array $file, string $tempFile, int $userId): string
    {
        $validation = $this->validateImage($file);
        if (! $validation['valid']) {
            throw new InvalidImageException((string) $validation['error']);
        }

        // Crop to a 300x300 square but keep the uploaded format.
        $mimeType = (string) $this->mimeDetector->detectMimeTypeFromFile($tempFile);
        $extension = ImageFormats::extensionFor($mimeType);

        ['bytes' => $processedImage, 'animated' => $avatarIsAnimated] = $this->processAvatarImage($tempFile, $extension);
        $dateBasedPath = $this->getDateBasedPath('avatars');

        // Row and file are committed together; final filename derives from the row id.
        $placeholder = $dateBasedPath.'/pending_avatar_'.$userId.'_'.uniqid().'.'.$extension;
        $fileHash = hash('sha256', $processedImage);

        // By reference: the cleanup below needs the path even when the transaction rolls back.
        $writtenPath = null;

        try {
            [$finalFilename, $finalPath] = DB::transaction(function () use (
                $processedImage, $dateBasedPath, $placeholder, $extension, $mimeType, $fileHash, $avatarIsAnimated, $userId, &$writtenPath
            ): array {
                $media = Media::create([
                    'file_hash' => $fileHash,
                    'file_name' => basename($placeholder),
                    'mime_type' => $mimeType !== '' ? $mimeType : 'image/jpeg',
                    'file_extension' => $extension,
                    'file_type' => 'image',
                    'media_type' => Media::TYPE_AVATAR,
                    'path' => $placeholder,
                    'file_size' => strlen($processedImage),
                    // `animated` is what tells the display layer never to format-convert this file.
                    'metadata' => ['width' => (int) config('orbita.images.avatar_size'), 'height' => (int) config('orbita.images.avatar_size'), 'animated' => $avatarIsAnimated],
                    'uploaded_by' => $userId,
                    'upload_ip' => request()->ip(),
                    'status' => 'active',
                ]);

                // Extension must match the encoded bytes or the stored path lies about its format.
                $finalFilename = HashId::encode((int) $media->id).'.'.$extension;
                $finalPath = $dateBasedPath.'/'.$finalFilename;
                $writtenPath = $finalPath;

                if ($this->disk->put($finalPath, $processedImage) === false) {
                    throw new \RuntimeException('Failed to write avatar to disk: '.$finalPath);
                }

                $media->update([
                    'file_name' => $finalFilename,
                    'path' => $finalPath,
                ]);

                return [$finalFilename, $finalPath];
            });
        } catch (\Throwable $e) {
            if ($writtenPath !== null && $this->disk->exists($writtenPath)) {
                $this->disk->delete($writtenPath);
            }

            throw $e;
        }

        return '/s/'.$finalPath;
    }

    /** @return array{bytes: string, animated: bool} */
    private function processAvatarImage(string $sourcePath, string $extension = 'jpg'): array
    {
        $image = $this->imageManager->decodePath($sourcePath);
        $animated = $image->isAnimated();
        $targetSize = (int) config('orbita.images.avatar_size');

        $image->cover($targetSize, $targetSize);

        return [
            'bytes' => (string) $image->encodeUsingFileExtension($extension, quality: (int) config('orbita.images.avatar_quality')),
            'animated' => $animated,
        ];
    }
}
