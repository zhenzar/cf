<?php

namespace App\Console\Commands;

use App\Models\Area;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportMapAreas extends Command
{
    protected $signature = 'app:import-map-areas {--dry-run}';
    protected $description = 'Import areas from the WorldMap wiki page that are not in the database';

    private array $mapAreaLinks = [];

    public function handle(): int
    {
        $this->info('Fetching WorldMap from wiki...');

        // Fetch the WorldMap page
        $html = $this->fetchWorldMapHtml();
        if (!$html) {
            $this->error('Failed to fetch WorldMap from wiki.');
            return 1;
        }

        // Parse area links from the map (now with wiki titles)
        $this->parseAreaLinksWithWikiTitles($html);

        $this->info('Found ' . count($this->mapAreaLinks) . ' unique areas on the map.');

        // Build comprehensive existing area lookup
        $existingAreas = $this->buildExistingAreaLookup();

        // Find missing areas
        $missingAreas = [];
        foreach ($this->mapAreaLinks as $wikiTitle => $data) {
            $url = $data['url'];
            $displayText = $data['display_text'];

            // Check if area exists by wiki title, URL, or display text
            if ($this->areaExists($wikiTitle, $url, $displayText, $existingAreas)) {
                continue;
            }

            // Skip fragment names (parts of larger names)
            if ($this->isFragmentName($displayText, $wikiTitle)) {
                continue;
            }

            // Use the wiki page title as the area name (more reliable than display text)
            $areaName = $this->cleanWikiTitle($wikiTitle);

            // Skip if still matches an existing area after cleaning
            if ($this->areaExists($areaName, $url, $areaName, $existingAreas)) {
                continue;
            }

            $missingAreas[$wikiTitle] = [
                'name' => $areaName,
                'url' => $url,
                'display_text' => $displayText,
            ];
        }

        if (empty($missingAreas)) {
            $this->info('All areas from the map are already in the database.');
            return 0;
        }

        $this->info('Found ' . count($missingAreas) . ' missing areas:');
        foreach ($missingAreas as $wikiTitle => $data) {
            $this->line("  - {$data['name']} (Wiki: {$wikiTitle})");
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No areas were imported.');
            return 0;
        }

        // Import missing areas
        $imported = 0;
        foreach ($missingAreas as $wikiTitle => $data) {
            $name = $data['name'];
            $url = $data['url'];

            // Determine realm from the area name or URL
            $realm = $this->guessRealm($name, $url);

            try {
                Area::create([
                    'name' => $name,
                    'realm' => $realm,
                    'min_level' => 1,
                    'max_level' => 51,
                    'url' => $url,
                ]);
                $imported++;
                $this->info("Imported: {$name} (Realm: {$realm})");
            } catch (\Exception $e) {
                $this->error("Failed to import {$name}: {$e->getMessage()}");
            }
        }

        $this->info("Successfully imported {$imported} areas.");
        return 0;
    }

    /**
     * Build lookup map for existing areas.
     */
    private function buildExistingAreaLookup(): array
    {
        $lookup = [
            'names' => [],
            'urls' => [],
            'wiki_titles' => [],
        ];

        $areas = Area::all();
        foreach ($areas as $area) {
            // Store normalized name
            $normalizedName = $this->normalizeForLookup($area->name);
            $lookup['names'][$normalizedName] = $area;

            // Store URL
            if ($area->url) {
                $lookup['urls'][strtolower($area->url)] = $area;
                // Also store wiki title from URL
                $wikiTitle = $this->extractWikiTitle($area->url);
                if ($wikiTitle) {
                    $lookup['wiki_titles'][$wikiTitle] = $area;
                }
            }
        }

        return $lookup;
    }

    /**
     * Check if area exists in the lookup.
     */
    private function areaExists(string $wikiTitle, string $url, string $displayText, array $lookup): bool
    {
        $normalizedWikiTitle = strtolower($wikiTitle);
        $normalizedUrl = strtolower($url);
        $normalizedDisplay = $this->normalizeForLookup($displayText);
        $cleanedName = $this->normalizeForLookup($this->cleanWikiTitle($wikiTitle));

        // Check by wiki title
        if (isset($lookup['wiki_titles'][$normalizedWikiTitle])) {
            return true;
        }

        // Check by URL
        if (isset($lookup['urls'][$normalizedUrl])) {
            return true;
        }

        // Check by display text
        if (isset($lookup['names'][$normalizedDisplay])) {
            return true;
        }

        // Check by cleaned wiki title
        if (isset($lookup['names'][$cleanedName])) {
            return true;
        }

        // Partial matching
        foreach ($lookup['names'] as $name => $area) {
            if (stripos($name, $normalizedDisplay) !== false || stripos($normalizedDisplay, $name) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize string for lookup comparison.
     */
    private function normalizeForLookup(string $text): string
    {
        return strtolower(str_replace([' ', "'", '-', '_'], '', $text));
    }

    /**
     * Clean wiki title to create proper area name.
     */
    private function cleanWikiTitle(string $wikiTitle): string
    {
        // Decode URL encoding
        $title = urldecode($wikiTitle);

        // Replace underscores and dashes with spaces
        $title = str_replace(['_', '-'], ' ', $title);

        // Remove common prefixes that aren't part of the name
        $prefixes = ['The', 'A', 'An'];
        foreach ($prefixes as $prefix) {
            if (stripos($title, $prefix . ' ') === 0) {
                // Keep it, just capitalize properly
                break;
            }
        }

        // Capitalize each word
        return Str::title(trim($title));
    }

    /**
     * Check if this is a fragment name (part of a larger area name).
     */
    private function isFragmentName(string $displayText, string $wikiTitle): bool
    {
        $text = strtolower(trim($displayText));
        $wiki = strtolower(trim($wikiTitle));

        // Common fragment words that shouldn't be standalone areas
        $fragments = [
            'the', 'of', 'a', 'an', 'in', 'on', 'at', 'to', 'for', 'with',
            'and', 'or', 'but', 'road', 'path', 'trail', 'keep', 'castle',
            'village', 'forest', 'mountains', 'plains', 'city', 'grove',
            'ruins', 'mines', 'temple', 'tower', 'sewers', 'woods', 'swamp',
            'island', 'cove', 'harbor', 'modan', 'galadon', 'udgaard',
            'hamsah', 'voralia', 'scarabaeus', 'thror', 'underdark',
            'ashesof', 'citadelof', 'mansionof', 'pyramidof', 'villageof',
            'loch', 'plains', 'fields', 'lake', 'river', 'sea', 'ocean',
            'north', 'south', 'east', 'west', 'northern', 'southern',
            'eastern', 'western', 'upper', 'lower', 'ancient', 'old',
            'new', 'dark', 'shadow', 'black', 'white', 'red', 'green',
            'blue', 'silver', 'gold', 'crystal', 'frozen', 'frigid',
            'burning', 'haunted', 'forgotten', 'abandoned', 'hidden',
            'lost', 'deep', 'high', 'low', 'great', 'little', 'big',
        ];

        // If display text is very different from wiki title, it's likely a fragment
        $displayNormalized = $this->normalizeForLookup($displayText);
        $wikiNormalized = $this->normalizeForLookup($wikiTitle);

        // If wiki title contains the display text, display text is likely a fragment
        if (strlen($displayText) < 5 && !str_contains($wiki, $text)) {
            return true;
        }

        // Check if it's just a fragment word
        if (in_array($text, $fragments)) {
            return true;
        }

        // Check if display text is a substring of wiki title (fragment)
        if (strlen($displayText) < strlen($wikiTitle) && str_contains($wiki, $text)) {
            // But allow if it's the main part (e.g., "Azhan" in "PyramidOfAzhan")
            if (strlen($displayText) >= 6) {
                return false;
            }
            return true;
        }

        return false;
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
     * Fetch the WorldMap HTML from the wiki.
     */
    private function fetchWorldMapHtml(): ?string
    {
        try {
            $response = Http::timeout(30)->get('http://wiki.qhcf.net/index.php?title=WorldMap');

            if (!$response->successful()) {
                return null;
            }

            return $response->body();
        } catch (\Exception $e) {
            $this->error("Error fetching WorldMap: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Parse area links from the WorldMap HTML with wiki titles.
     */
    private function parseAreaLinksWithWikiTitles(string $html): void
    {
        // Find all links in the map content
        // The map is in a <pre> block with HTML wiki links
        if (preg_match('/<pre[^>]*>(.+?)<\/pre>/si', $html, $m)) {
            $mapContent = $m[1];

            // Find all HTML wiki links in the map
            // Pattern captures both URL (with wiki title) and display text
            preg_match_all('/<a[^>]+href="(http:\/\/wiki\.qhcf\.net\/index\.php\?title=([^"&]+)[^"]*)"[^>]*>([^<]+)<\/a>/', $mapContent, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $url = $match[1];
                $wikiTitle = urldecode($match[2]);  // The actual wiki page title
                $displayText = trim($match[3]);      // The display text on the map

                // Skip author names
                $skipNames = ['RobertDunn', 'Zendrac', 'Yhorian', 'DurNominator', 'Robert', 'Dunn'];
                if (in_array($wikiTitle, $skipNames) || in_array($displayText, $skipNames)) {
                    continue;
                }

                // Store by wiki title (more reliable than display text)
                if (!isset($this->mapAreaLinks[$wikiTitle])) {
                    $this->mapAreaLinks[$wikiTitle] = [
                        'url' => $url,
                        'display_text' => $displayText,
                        'wiki_title' => $wikiTitle,
                    ];
                }
            }
        }
    }

    /**
     * Clean up the area name from link text.
     */
    private function cleanAreaName(string $text): string
    {
        // Remove any trailing/leading whitespace and common connectors
        $text = trim($text);

        // Remove common partial indicators like ---, +++, etc.
        $text = preg_replace('/[-+\|]+/', ' ', $text);

        // Clean up extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Capitalize each word
        $text = Str::title($text);

        return trim($text);
    }

    /**
     * Guess the realm based on area name and URL.
     */
    private function guessRealm(string $name, string $url): string
    {
        // Extract the page title from the URL
        if (preg_match('/title=([^&]+)/', $url, $m)) {
            $pageTitle = urldecode($m[1]);

            // Known realm patterns
            $realmPatterns = [
                'Udgaard' => ['Udgaard', 'Shire', 'GolGalath', 'Arendyl', 'Seantryn'],
                'Galadon' => ['Galadon', 'Balator', 'Arkham', 'Lallenyha'],
                'Hamsah' => ['Hamsah', 'Corte', 'TirTalath', 'Dagdan'],
                'Voralia' => ['Voralia', 'Dairein', 'Voralian'],
                'Underdark' => ['Underdark', 'Velkyn', 'Teth', 'CoralPalace'],
                'Thror' => ['Thror', 'Ostalagiah', 'Galadon', 'Dwarf'],
                'Scarabaeus' => ['Scarabaeus', 'DarkWoods', 'Whistlewood', 'Pyramid', 'Hell'],
                'Cragstone' => ['Cragstone', 'Blingden'],
                'Ishuli' => ['Ishuli', 'Aubade', 'Qhabiszan', 'Saurian'],
                'ShaidarHaran' => ['Shaidar', 'Barovia', 'Talshidar', 'Zakiim'],
                'Pico' => ['Pico', 'Nonviel', 'Jade', 'Xvart', 'Wastes'],
                'Ysaloerye' => ['Ysaloerye', 'Northern', 'Foothills'],
                'Mayesha' => ['Mayesha', 'Aratouldain', 'Veran', 'Velkyn'],
                'Laurella' => ['Laurella', 'Arendyl', 'Darsylon'],
                'Fjarn' => ['Fjarn', 'Maethien', 'Siege'],
                'Kastellyn' => ['Kastellyn', 'Feanwyyn', 'Grynmear'],
                'Ashaxreyn' => ['Ashaxreyn', 'Calandaryl', 'Nizarrsh'],
                'Nepenthe' => ['Nepenthe', 'Balator', 'Ysigrath', 'Kuo'],
                'Iukuru' => ['Iukuru', 'Pine', 'Octagonal'],
                'Stellyx' => ['Stellyx', 'Whistlewood'],
                'Zulghinlour' => ['Zulghinlour', 'Talshidar'],
                'Sevarecan' => ['Sevarecan', 'Delar'],
                'Selric' => ['Selric', 'Ugruk', 'Drogran'],
                'Astein' => ['Astein', 'Ceawlin'],
                'Montolio' => ['Montolio', 'Tabershaw', 'Spiderhaunt'],
                'Nazmorghul' => ['Nazmorghul', 'Mortorn'],
                'Khargqwzxdds' => ['Khargqwzxdds', 'Mausoleum'],
                'Lloth' => ['Lloth', 'Frigid'],
                'Agathocles' => ['Agathocles', 'Corte'],
                'Kadizine' => ['Kadizine', 'Maltrak'],
                'Holtzendorff' => ['Holtzendorff', 'Akan'],
                'Vilhazarog' => ['Vilhazarog', 'Silverwood'],
                'Acallsho' => ['Acallsho', 'Moudrilar'],
                'Eryndorial' => ['Eryndorial', 'Crystal'],
                'Taerin' => ['Taerin', 'Outlying'],
                'Jacynth' => ['Jacynth', 'Graveyard'],
                'Amaranthe' => ['Amaranthe', 'Sewers', 'Glauruk'],
                'Valguarnera' => ['Valguarnera', 'Kteng'],
                'Arvam' => ['Arvam', 'Zakiim'],
                'Gadinias' => ['Gadinias', 'Dranettie'],
                'Macheath' => ['Macheath', 'Dagdan'],
                'Teiphicker' => ['Teiphicker', 'Deep'],
                'Amlaruil' => ['Amlaruil', 'Elemental'],
                'Radickon' => ['Radickon', 'Kiadana'],
                'Savraeth' => ['Savraeth', 'Mists'],
                'Quezzumpliet' => ['Quezzumpliet', 'Balator'],
                'Gherian' => ['Gherian', 'Delar', 'Yzekon'],
                'Sabiene' => ['Sabiene', 'Azuremain'],
                'Rogardian' => ['Rogardian', 'Northern'],
                'Blachmianan' => ['Blachmianan', 'Melkhur'],
                'Shokai' => ['Shokai', 'Sorrow', 'Araile', 'Paths'],
                'Cyra' => ['Cyra', 'Aturi'],
                'Intronan' => ['Intronan', 'Bandit'],
                'Divox' => ['Divox', 'Blackwater'],
                'Ishmael' => ['Ishmael', 'Halfling'],
                'Grurk' => ['Grurk', 'Crypts'],
                'Phaelim' => ['Phaelim', 'Maethien'],
                'Choranek' => ['Choranek', 'Amaranth'],
                'Andrlos' => ['Andrlos', 'Terradian'],
                'Muuloc' => ['Muuloc', 'Dralkar'],
                'Dreaa' => ['Dreaa', 'Twilight'],
                'Proserpina' => ['Proserpina', 'Oryx'],
                'Vahlen' => ['Vahlen', 'Felar'],
                'Aarn' => ['Aarn', 'Consortium'],
                'Thrak' => ['Thrak', 'Loch', 'Sebeok', 'Kast', 'High'],
                'Rayihn' => ['Rayihn', 'Trinil', 'Ayr'],
                'Zesam' => ['Zesam', 'Arkham'],
                'Vass' => ['Vass', 'Rad', 'Seantryn'],
                'Vassagon' => ['Vassagon', 'Lumberyard', 'Deep'],
                'Shea' => ['Shea', 'Hillcrest'],
                'Poetry' => ['Poetry', 'Evermoon'],
                'Yanoreth' => ['Yanoreth', 'Blackclaw'],
                'Nreykre' => ['Nreykre', 'Frozen'],
                'Pet' => ['Pet', 'Aeon', 'Pass', 'Udgaard'],
                'Tureanthen' => ['Tureanthen', 'Sitran', 'Ashes'],
                'Nelt' => ['Nelt', 'Goblin'],
                'Cador' => ['Cador', 'Eshval', 'Kobold'],
                'Bria' => ['Bria', 'Foothills'],
                'Rarywey' => ['Rarywey', 'Aubade'],
                'Thaedan' => ['Thaedan', 'Workshop'],
                'Whildur' => ['Whildur', 'Evergrove'],
                'Farigno' => ['Farigno', 'Khardrath'],
                'Destuvius' => ['Destuvius', 'Forgotten'],
                'Rahsael' => ['Rahsael', 'Shadow', 'Battlefield'],
                'Ishuli' => ['Ishuli', 'Saurian', 'Goblin'],
                'Corrlaan' => ['Corrlaan', 'Arena'],
                'Strienat' => ['Strienat', 'Emerald'],
                'Yean' => ['Yean', 'Udgaardian'],
                'Nythos' => ['Nythos', 'Targeth'],
                'Drehir' => ['Drehir', 'Dhumlar'],
                'Malakhi' => ['Malakhi', 'Shaeria'],
                'Nnaeshuk' => ['Nnaeshuk', 'Enpolad'],
                'Nimbus' => ['Nimbus', 'Cragstone', 'Battlefield'],
                'Twist' => ['Twist', 'Forest', 'Ashes'],
                'Justin' => ['Justin', 'Sea', 'Coral'],
                'Saldradien' => ['Saldradien', 'Coastal'],
                'Guerric' => ['Guerric', 'Aryth'],
                'Soucivi' => ['Soucivi', 'Fhalaugash'],
                'Acallsho' => ['Acallsho', 'Moudrilar'],
                'Achelon' => ['Achelon', 'Strange'],
                'Jullias' => ['Jullias', 'Aldevari', 'Violet'],
                'Sattanos' => ['Sattanos', 'Orsil', 'Dragon', 'Grove'],
                'Jormungandr' => ['Jormungandr', 'Goblin'],
                'Dhuuston' => ['Dhuuston', 'Spire'],
                'Neajess' => ['Neajess', 'Qhabiszan'],
            ];

            foreach ($realmPatterns as $realm => $patterns) {
                foreach ($patterns as $pattern) {
                    if (stripos($pageTitle, $pattern) !== false || stripos($name, $pattern) !== false) {
                        return $realm;
                    }
                }
            }
        }

        return 'Unknown';
    }
}
