<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Support\Facades\Http;

class MapController extends Controller
{
    /**
     * Display the world map page.
     */
    public function index()
    {
        // Fetch areas for linking on the map
        $areas = Area::orderBy('name')->get();
        $areaMap = $areas->keyBy(function ($area) {
            return strtolower(str_replace([' ', "'", '-', '_'], '', $area->name));
        });

        // Get the raw map content from the wiki
        $mapContent = $this->fetchWorldMap();

        return view('maps.index', compact('areas', 'areaMap', 'mapContent'));
    }

    /**
     * Fetch the world map from the wiki.
     */
    private function fetchWorldMap(): string
    {
        try {
            $response = Http::timeout(30)->get('http://wiki.qhcf.net/index.php?title=WorldMap');

            if (! $response->successful()) {
                return '';
            }

            $html = $response->body();

            // Extract the map content (the ASCII art is in a pre/code block)
            if (preg_match('/<pre[^>]*>(.+?)<\/pre>/si', $html, $m)) {
                return $this->cleanMapContent($m[1]);
            }

            // Fallback: try to find the map in the content
            if (preg_match('/MAP OF THERA(.+?)Original map by/si', $html, $m)) {
                return 'MAP OF THERA' . $this->cleanMapContent($m[1]) . "\nOriginal map by";
            }

            return '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Clean the map content for display.
     */
    private function cleanMapContent(string $content): string
    {
        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove any HTML tags
        $content = strip_tags($content);

        return trim($content);
    }
}
