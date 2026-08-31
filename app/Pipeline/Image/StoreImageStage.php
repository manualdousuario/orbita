<?php

declare(strict_types=1);

namespace App\Pipeline\Image;

use App\Models\Media;
use App\Support\HashId;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

/**
 * Writes the original image bytes to disk and persists the media record.
 */
final class StoreImageStage
{
    private const POSTS_BASE = 'posts';

    public function __construct(
        private Filesystem $disk,
    ) {}

    public function __invoke(ImageUploadContext $ctx): ImageUploadContext
    {
        if ($ctx->result !== null) {
            return $ctx;
        }

        $date = new \DateTime;
        $dateBasedPath = sprintf(
            '%s/%s/%s/%s',
            self::POSTS_BASE,
            $date->format('Y'),
            $date->format('m'),
            $date->format('d'),
        );
        $ctx->dateBasedPath = $dateBasedPath;

        $extension = $ctx->extension !== '' ? $ctx->extension : 'jpg';

        // Stored bytes are the original; EXIF folds into the JSON metadata, no second decode.
        $metadata = [
            'width' => $ctx->imageWidth,
            'height' => $ctx->imageHeight,
            'animated' => $ctx->isAnimated,
        ];

        if ($ctx->exif !== []) {
            $metadata['exif'] = $ctx->exif;
        }

        // Row and file succeed or fail together; the disk write holds the row's locks.
        $placeholder = $dateBasedPath.'/pending_'.uniqid().'.'.$extension;

        // By reference: the cleanup below needs the path even when the transaction rolls back.
        $writtenPath = null;

        try {
            [$mediaId, $finalFilename, $finalPath] = DB::transaction(function () use ($ctx, $dateBasedPath, $extension, $placeholder, $metadata, &$writtenPath): array {
                $media = Media::create([
                    'file_hash' => $ctx->fileHash,
                    'file_name' => basename($placeholder),
                    'mime_type' => $ctx->mimeType !== '' ? $ctx->mimeType : 'image/jpeg',
                    'file_extension' => $extension,
                    'file_type' => 'image',
                    'media_type' => Media::TYPE_POST,
                    'path' => $placeholder,
                    'file_size' => strlen($ctx->processedImageBytes),
                    'metadata' => $metadata,
                    'uploaded_by' => $ctx->userId ?? 1,
                    'upload_ip' => request()->ip(),
                    'status' => 'active',
                ]);

                $mediaId = (int) $media->id;
                $finalFilename = HashId::encode($mediaId).'.'.$extension;
                $finalPath = $dateBasedPath.'/'.$finalFilename;

                // put() creates intermediate directories; some adapters signal failure with false.
                $writtenPath = $finalPath;

                if ($this->disk->put($finalPath, $ctx->processedImageBytes) === false) {
                    throw new \RuntimeException('Failed to write image to disk: '.$finalPath);
                }

                $media->update([
                    'file_name' => $finalFilename,
                    'path' => $finalPath,
                ]);

                return [$mediaId, $finalFilename, $finalPath];
            });
        } catch (\Throwable $e) {
            // The row is rolled back; drop the orphaned bytes too, if any landed.
            if ($writtenPath !== null && $this->disk->exists($writtenPath)) {
                $this->disk->delete($writtenPath);
            }

            throw $e;
        }

        $ctx->mediaId = $mediaId;
        $ctx->finalFilename = $finalFilename;
        $ctx->finalPath = $finalPath;

        $ctx->result = [
            'success' => true,
            'path' => '/'.$finalPath,
            'filename' => $finalFilename,
            'media_id' => $mediaId,
        ];

        return $ctx;
    }
}
