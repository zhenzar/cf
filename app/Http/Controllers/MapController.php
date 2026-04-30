<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Support\Facades\Http;

class MapController extends Controller
{
    private array $linkMapping = [];

    /**
     * Display the world map page.
     */
    public function index()
    {
        // Fetch areas for linking on the map
        $areas = Area::orderBy('name')->get();

        // Build normalized area lookup map
        $areaMap = [];
        foreach ($areas as $area) {
            $normalized = $this->normalizeName($area->name);
            $areaMap[$normalized] = $area;
            // Also store by URL title if available
            if ($area->url) {
                $urlTitle = $this->extractWikiTitle($area->url);
                if ($urlTitle) {
                    $areaMap[strtolower($urlTitle)] = $area;
                }
            }
        }

        // Get the map content with links parsed and converted
        $mapData = $this->fetchWorldMapWithLinks($areaMap);

        return view('maps.index', [
            'areas' => $areas,
            'areaMap' => $areaMap,
            'mapContent' => $mapData['content'],
            'linkMapping' => $mapData['links'],
        ]);
    }

    /**
     * Fetch the world map from the wiki and convert links to internal.
     */
    private function fetchWorldMapWithLinks(array $areaMap): array
    {
        try {
            $response = Http::timeout(30)->get('http://wiki.qhcf.net/index.php?title=WorldMap');

            if (! $response->successful()) {
                return ['content' => '', 'links' => []];
            }

            $html = $response->body();

            // Extract the map content (the ASCII art is in a pre block with HTML links)
            if (preg_match('/<pre[^>]*>(.+?)<\/pre>/si', $html, $m)) {
                $mapHtml = $m[1];
                return $this->processMapLinks($mapHtml, $areaMap);
            }

            return ['content' => '', 'links' => []];
        } catch (\Exception $e) {
            return ['content' => '', 'links' => []];
        }
    }

    /**
     * Process map HTML links and convert to internal links.
     */
    private function processMapLinks(string $mapHtml, array $areaMap): array
    {
        $links = [];

        // Find all wiki links in the map
        // Pattern: <a href="http://wiki.qhcf.net/index.php?title=PageName" ...>Display Text</a>
        preg_match_all('/<a[^>]+href="(http:\/\/wiki\.qhcf\.net\/index\.php\?title=([^"&]+)[^"]*)"[^>]*>([^<]+)<\/a>/', $mapHtml, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $fullUrl = $match[1];
            $wikiTitle = urldecode($match[2]);
            $displayText = trim($match[3]);

            // Try to find matching area
            $area = $this->findAreaByWikiTitle($wikiTitle, $areaMap);

            if ($area) {
                $internalUrl = route('areas.wiki', $area);
                $links[$fullUrl] = [
                    'internal_url' => $internalUrl,
                    'area_id' => $area->id,
                    'area_name' => $area->name,
                    'display_text' => $displayText,
                ];
            } else {
                // Area not in database - link to external wiki for now
                $links[$fullUrl] = [
                    'internal_url' => null,
                    'external_url' => $fullUrl,
                    'wiki_title' => $wikiTitle,
                    'display_text' => $displayText,
                ];
            }
        }

        // Replace links in the HTML
        $processedContent = $mapHtml;
        foreach ($links as $originalUrl => $linkData) {
            $targetUrl = $linkData['internal_url'] ?? $linkData['external_url'];
            $pattern = '/<a[^>]+href="' . preg_quote($originalUrl, '/') . '"[^>]*>/';
            $replacement = '<a href="' . $targetUrl . '" class="map-link" data-wiki-title="' . ($linkData['wiki_title'] ?? $linkData['area_name'] ?? '') . '">';
            $processedContent = preg_replace($pattern, $replacement, $processedContent);
        }

        // Clean up but preserve our converted links
        $processedContent = $this->cleanMapContentPreserveLinks($processedContent);

        return [
            'content' => $processedContent,
            'links' => $links,
        ];
    }

    /**
     * Find area by wiki title.
     */
    private function findAreaByWikiTitle(string $wikiTitle, array $areaMap): ?Area
    {
        // Try exact match on normalized wiki title
        $normalized = strtolower($wikiTitle);
        if (isset($areaMap[$normalized])) {
            return $areaMap[$normalized];
        }

        // Try variations
        $variations = [
            $normalized,
            str_replace(['_', '-', ' '], '', $normalized),
            str_replace(['of', 'the', 'a', 'an', 'in', 'on', 'at'], '', $normalized),
        ];

        foreach ($variations as $var) {
            $var = trim($var);
            if (isset($areaMap[$var])) {
                return $areaMap[$var];
            }
        }

        // Search through all areas for partial match
        foreach ($areaMap as $key => $area) {
            if (stripos($key, $normalized) !== false || stripos($normalized, $key) !== false) {
                return $area;
            }
        }

        return null;
    }

    /**
     * Extract wiki title from URL.
     */
    private function extractWikiTitle(string $url): ?string
    {
        if (preg_match('/title=([^&]+)/', $url, $m)) {
            return strtolower(urldecode($m[1]));
        }
        return null;
    }

    /**
     * Normalize area name for comparison.
     */
    private function normalizeName(string $name): string
    {
        return strtolower(str_replace([' ', "'", '-', '_'], '', $name));
    }

    /**
     * Clean the map content while preserving links.
     */
    private function cleanMapContentPreserveLinks(string $content): string
    {
        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove class/mw-redirect attributes from links but keep href
        $content = preg_replace('/<a([^>]*)\s+class="[^"]*"([^>]*)>/i', '<a$1$2>', $content);
        $content = preg_replace('/<a([^>]*)\s+title="[^"]*"([^>]*)>/i', '<a$1$2>', $content);

        // Remove any other HTML tags except <a>
        $content = preg_replace('/<(?!a\s|a>|\/a>)[^>]+>/i', '', $content);

        return trim($content);
    }
}
