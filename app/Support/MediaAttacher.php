<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Comment;
use App\Models\Post;
use App\Services\ImageService;

/**
 * Attaches uploaded images to a post or comment via ImageService.
 */
class MediaAttacher
{
    public function __construct(private readonly ImageService $images) {}

    /**
     * Uploads and attaches the given images, respecting the per-post limit.
     *
     * @param  array<int, array<string, mixed>>  $uploads  $_FILES-style entries
     * @param  array<int, ?string>  $captions  optional captions, keyed like $uploads
     * @return int number of images actually attached
     */
    public function attach(Post|Comment $target, array $uploads, int $userId, array $captions = []): int
    {
        $limit = (int) config('orbita.limit_images_post', 5);
        $order = (int) $target->media()->count();
        $attached = 0;

        foreach ($uploads as $i => $file) {
            if ($order >= $limit) {
                break;
            }

            $result = $this->images->uploadImage($file, 'posts', $userId);

            if (($result['success'] ?? false) && ! empty($result['media_id'])) {
                $mediaId = (int) $result['media_id'];

                // The pivot is a set (unique post_id+media_id). Uploads no longer dedupe, so a
                // repeated media_id cannot occur here, but syncWithoutDetaching costs nothing
                // extra and keeps a second attach from ever becoming a 500.
                $caption = $captions[$i] ?? null;
                $target->media()->syncWithoutDetaching([
                    $mediaId => [
                        'display_order' => $order,
                        'caption' => $caption !== null && $caption !== '' ? $caption : null,
                    ],
                ]);
                $order++;
                $attached++;
            }
        }

        return $attached;
    }
}
