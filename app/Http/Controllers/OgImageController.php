<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\OgImageService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the post's Open Graph image on demand.
 */
class OgImageController extends Controller
{
    /** How long a request will wait for whoever is already rendering this image. */
    private const LOCK_WAIT_SECONDS = 12;

    /** How long the renderer's bytes persist for the requests queued behind it. */
    private const HANDOFF_SECONDS = 30;

    public function __invoke(string $hashid): Response
    {
        $post = Post::query()
            ->with('user:id,username,display_name,avatar_url')
            ->where('hashid', $hashid)
            ->whereNull('version_of')
            ->whereIn('status', ['published', 'closed'])
            ->firstOrFail(['id', 'hashid', 'title', 'content', 'user_id', 'published_at']);

        $handoffKey = 'og:png:'.$post->hashid;

        $bytes = $this->readHandoff($handoffKey);
        if ($bytes !== null) {
            return $this->respond($bytes);
        }

        $lock = Cache::lock('og:lock:'.$post->hashid, self::LOCK_WAIT_SECONDS);

        try {
            $lock->block(self::LOCK_WAIT_SECONDS);
        } catch (LockTimeoutException) {
            // Lock holder timed out; render unguarded rather than return 5xx.
            return $this->respond($this->render($post));
        }

        try {
            // The holder may have finished between our read above and our acquiring the lock.
            $bytes = $this->readHandoff($handoffKey);

            if ($bytes === null) {
                $bytes = $this->render($post);
                $this->writeHandoff($handoffKey, $bytes);
            }
        } finally {
            $lock->release();
        }

        return $this->respond($bytes);
    }

    /**
     * Read the handoff bytes, stored as base64 for the database cache store.
     */
    private function readHandoff(string $key): ?string
    {
        $encoded = Cache::get($key);

        if (! is_string($encoded)) {
            return null;
        }

        $bytes = base64_decode($encoded, true);

        return $bytes === false ? null : $bytes;
    }

    private function writeHandoff(string $key, string $bytes): void
    {
        Cache::put($key, base64_encode($bytes), now()->addSeconds(self::HANDOFF_SECONDS));
    }

    private function render(Post $post): string
    {
        $author = $post->user;

        return app(OgImageService::class)->render(
            (string) $post->title,
            $this->description((string) $post->content),
            (string) ($author?->display_name ?: $author?->username),
            (string) $author?->username,
            $author?->avatar_url,
            $post->published_at,
        );
    }

    /**
     * Strip content to plain text without the Markdown mention parser.
     */
    private function description(string $content): string
    {
        return Str::limit(trim(strip_tags(html_entity_decode($content))), (int) config('orbita.ogimage.description_length'), '');
    }

    private function respond(string $bytes): Response
    {
        return new Response($bytes, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
