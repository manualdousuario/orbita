<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves generated sitemap files.
 */
class SitemapController extends Controller
{
    public function __construct(private readonly SitemapService $sitemap) {}

    public function index(): BinaryFileResponse|Response
    {
        return $this->serve(SitemapService::INDEX);
    }

    public function pages(): BinaryFileResponse|Response
    {
        return $this->serve(SitemapService::PAGES);
    }

    public function posts(int $page): BinaryFileResponse|Response
    {
        return $this->serve(SitemapService::postsFile($page));
    }

    private function serve(string $file): BinaryFileResponse|Response
    {
        try {
            $path = $this->sitemap->ensure($file);
        } catch (LockTimeoutException) {
            return response('Sitemap is being generated, try again shortly.', 503)
                ->header('Retry-After', '30');
        }

        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
