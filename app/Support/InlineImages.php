<?php

namespace App\Support;

use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Decorates rendered HTML: resizes, wraps, and adds zoom/edit links to images.
 */
class InlineImages
{
    public const COMMENT_MAX = 250;

    public const POST_MAX = 450;

    /**
     * Rewrites inline images in rendered HTML with resize, zoom, and edit links.
     *
     * @param  string  $html  rendered Markdown HTML (may contain inline `<img>`)
     * @param  Collection<int, Media>|iterable<int, Media>|null  $media  the content owner's media
     * @param  User|null  $viewer  who is looking at the page (staff get the admin edit shortcut)
     * @param  int|null  $maxPx  display cap for the longest side; null leaves the image untouched
     */
    public static function decorate(string $html, $media = null, ?User $viewer = null, ?int $maxPx = null): string
    {
        $mediaByPath = self::mediaByPath($media);
        $staff = $viewer !== null && $viewer->isStaff();

        if ($maxPx === null && ! $staff) {
            return $html;
        }

        return (string) preg_replace_callback('/(<a\b[^>]*>\s*)?<img\b[^>]*>/i', static function (array $m) use ($mediaByPath, $staff, $maxPx): string {
            if (($m[1] ?? '') !== '') {
                return $m[0];
            }

            $img = $m[0];

            if (! preg_match('/\bsrc=["\']([^"\']+)["\']/i', $img, $url)) {
                return $img;
            }

            $path = self::pathFromUrl((string) $url[1]);
            $media = $path !== null ? ($mediaByPath[$path] ?? null) : null;

            $zoomable = false;

            if ($maxPx !== null && $path !== null) {
                $img = self::resize($img, $path, $media, $maxPx);
                $img = self::zoomWrap($img, $path, $media);
                $zoomable = true;
            }

            $edit = $staff && $media instanceof Media ? self::editLink($media) : '';

            $out = $img;

            if ($zoomable || $edit !== '') {
                $out = '<span class="md-img-wrap">'.$out.$edit.'</span>';
            }

            return $out;
        }, $html);
    }

    private static function resize(string $img, string $path, ?Media $media, int $maxPx): string
    {
        if ($media instanceof Media && ImageUrl::isAnimated($media)) {
            return $img;
        }

        $box = $maxPx * 2;
        $opts = ['width' => $box, 'height' => $box, 'fit' => 'contain'];

        $src = $media instanceof Media
            ? ImageUrl::media($media, $opts)
            : ImageUrl::path($path, $opts);

        $img = (string) preg_replace(
            '/\bsrc=["\'][^"\']*["\']/i',
            'src="'.e($src).'"',
            $img,
            1
        );

        return self::withIntrinsicSize($img, $media);
    }

    private static function withIntrinsicSize(string $img, ?Media $media): string
    {
        if (! $media instanceof Media || preg_match('/\bwidth=/i', $img) === 1) {
            return $img;
        }

        $metadata = is_array($media->metadata) ? $media->metadata : [];
        $width = (int) ($metadata['width'] ?? 0);
        $height = (int) ($metadata['height'] ?? 0);

        if ($width <= 0 || $height <= 0) {
            return $img;
        }

        return (string) preg_replace(
            '/<img\b/i',
            '<img width="'.$width.'" height="'.$height.'"',
            $img,
            1
        );
    }

    private static function zoomWrap(string $img, string $path, ?Media $media): string
    {
        $url = e(ImageUrl::original($path));
        $metadata = $media instanceof Media && is_array($media->metadata) ? $media->metadata : [];
        $width = (int) ($metadata['width'] ?? 0);
        $height = (int) ($metadata['height'] ?? 0);

        $dimensions = $width > 0 && $height > 0
            ? ' data-pswp-width="'.$width.'" data-pswp-height="'.$height.'"'
            : '';

        return '<a href="'.$url.'" target="_blank" rel="noopener" class="pswp-item"'.$dimensions
            .' aria-label="Ampliar imagem">'.$img.'</a>';
    }

    private static function editLink(Media $media): string
    {
        $url = e(MediaResource::getUrl('edit', ['record' => $media]));

        return '<a href="'.$url.'" target="_blank" rel="noopener" class="media-edit-btn"'
            .' title="Editar mídia no admin" aria-label="Editar mídia no admin">'
            .'<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>'
            .'</a>';
    }

    /**
     * @param  Collection<int, Media>|iterable<int, Media>|null  $media
     * @return array<string, Media>
     */
    private static function mediaByPath($media): array
    {
        if ($media === null) {
            return [];
        }

        $map = [];
        foreach ($media as $m) {
            if ($m instanceof Media) {
                $map[(string) $m->path] = $m;
            }
        }

        return $map;
    }

    private static function pathFromUrl(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        if (str_starts_with($path, '/s/')) {
            return substr($path, 3);
        }

        return null;
    }
}
