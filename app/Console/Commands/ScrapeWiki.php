<?php

namespace App\Console\Commands;

use App\Models\Area;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ScrapeWiki extends Command
{
    protected $signature = 'app:scrape-wiki {--area=} {--all} {--force}';
    protected $description = 'Scrape wiki pages from wiki.qhcf.net for areas';

    public function handle(): int
    {
        if ($this->option('area')) {
            $areas = Area::where('id', $this->option('area'))->get();
        } elseif ($this->option('all')) {
            $areas = Area::whereNotNull('url')->get();
        } else {
            $areas = Area::whereNotNull('url')
                ->where(function ($q) {
                    $q->whereNull('wiki_fetched_at')
                        ->orWhere('wiki_fetched_at', '<', now()->subWeek());
                })->get();
        }

        $this->info("Processing {$areas->count()} areas...");
        $bar = $this->output->createProgressBar($areas->count());

        $success = 0;
        $failed = 0;

        foreach ($areas as $area) {
            if (! $this->option('force') && $area->wiki_fetched_at && !$this->option('area')) {
                $bar->advance();
                continue;
            }

            $result = $this->scrapeArea($area);
            if ($result) {
                $success++;
            } else {
                $failed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Success: {$success}, Failed: {$failed}");

        return 0;
    }

    private function scrapeArea(Area $area): bool
    {
        try {
            $response = Http::timeout(30)->get($area->url);

            if (! $response->successful()) {
                $this->error("Failed to fetch: {$area->url} (HTTP {$response->status()})");
                return false;
            }

            $html = $response->body();

            // Extract content from the MediaWiki page
            $content = $this->extractWikiContent($html);

            if (empty($content)) {
                $this->warn("No content found for: {$area->name}");
                return false;
            }

            // Extract title
            if (preg_match('/<title>(.+?)<\/title>/si', $html, $m)) {
                $area->wiki_title = trim(str_replace('- QHCF Wiki', '', $m[1]));
            }

            $area->wiki_content = $content;
            $area->wiki_fetched_at = now();
            $area->save();

            return true;
        } catch (\Exception $e) {
            $this->error("Error scraping {$area->name}: {$e->getMessage()}");
            return false;
        }
    }

    private function extractWikiContent(string $html): string
    {
        // Try to find the main content div (MediaWiki mw-parser-output)
        if (preg_match('/<div[^>]*class="[^"]*mw-parser-output[^"]*"[^>]*>(.+?)<\/div>\s*<\/div>\s*<\/div>/si', $html, $m)) {
            $content = $m[1];
        } elseif (preg_match('/<div[^>]*id="mw-content-text"[^>]*>(.+?)<\/div>\s*<\/div>/si', $html, $m)) {
            $content = $m[1];
        } elseif (preg_match('/<div[^>]*id="content"[^>]*>(.+?)<\/div>\s*<\/div>\s*<\/div>/si', $html, $m)) {
            $content = $m[1];
        } else {
            // Fallback: extract body content
            $content = $html;
        }

        // Clean up the content
        $content = $this->cleanContent($content);

        return $content;
    }

    private function cleanContent(string $content): string
    {
        // Remove edit links
        $content = preg_replace('/<span[^>]*class="[^"]*editsection[^"]*"[^>]*>.*?<\/span>/si', '', $content);

        // Remove script tags
        $content = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $content);

        // Remove style tags
        $content = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $content);

        // Remove navigation elements
        $content = preg_replace('/<div[^>]*class="[^"]*navbox[^"]*"[^>]*>.*?<\/div>/si', '', $content);
        $content = preg_replace('/<table[^>]*class="[^"]*navbox[^"]*"[^>]*>.*?<\/table>/si', '', $content);

        // Remove toc (table of contents)
        $content = preg_replace('/<div[^>]*id="toc"[^>]*>.*?<\/div>/si', '', $content);

        // Remove jump links
        $content = preg_replace('/<span[^>]*class="[^"]*mw-jump-link[^"]*"[^>]*>.*?<\/span>/si', '', $content);

        // Remove empty paragraphs
        $content = preg_replace('/<p[^>]*>\s*<\/p>/si', '', $content);

        // Fix relative links to absolute
        $content = preg_replace('/href="\/index\.php\?/', 'href="/wiki/', $content);

        return trim($content);
    }
}
