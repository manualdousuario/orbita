<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;

/**
 * Generates the sitemap index and paginated post sitemaps.
 */
class SitemapGenerate extends Command
{
    protected $signature = 'orbita:sitemap-generate {--dir= : Output directory (defaults to the storage cache)}';

    protected $description = 'Generate the sitemap index and paginated post sitemaps.';

    public function handle(SitemapService $sitemap): int
    {
        $postFiles = $sitemap->generate($this->option('dir') ?: null);

        $this->info("Sitemap written: {$postFiles} post file(s) + pages + index.");

        return self::SUCCESS;
    }
}
