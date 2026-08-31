<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\MetaTagsService;
use App\Support\Markdown;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

/**
 * Static DB-backed pages (guidelines, about, etc.) rendered from Markdown.
 */
class PageController extends Controller
{
    /**
     * GET /page/{slug} — render an active page; 404 when missing or inactive.
     */
    public function show(string $slug): View
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $contentHtml = Markdown::toHtml((string) $page->content);
        $excerpt = Str::limit(strip_tags($contentHtml), 200);
        $meta = app(MetaTagsService::class)->forStaticPage($page, $excerpt);

        return view('pages.show', [
            'page' => $page,
            'contentHtml' => $contentHtml,
            'meta' => $meta,
        ]);
    }
}
