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

        // Parse area links from the map
        $this->parseAreaLinks($html);

        $this->info('Found ' . count($this->mapAreaLinks) . ' unique areas on the map.');

        // Get existing areas
        $existingAreas = Area::pluck('name')->map(fn($name) => strtolower($name))->all();
        $existingAreaUrls = Area::whereNotNull('url')->pluck('url')->map(fn($url) => strtolower($url))->all();

        // Find missing areas
        $missingAreas = [];
        foreach ($this->mapAreaLinks as $areaName => $url) {
            $normalizedName = strtolower($areaName);
            $normalizedUrl = strtolower($url);

            // Check if area exists by name or URL
            $exists = in_array($normalizedName, $existingAreas) ||
                     in_array($normalizedUrl, $existingAreaUrls);

            if (!$exists) {
                $missingAreas[$areaName] = $url;
            }
        }

        if (empty($missingAreas)) {
            $this->info('All areas from the map are already in the database.');
            return 0;
        }

        $this->info('Found ' . count($missingAreas) . ' missing areas:');
        foreach ($missingAreas as $name => $url) {
            $this->line("  - {$name} ({$url})");
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No areas were imported.');
            return 0;
        }

        // Import missing areas
        $imported = 0;
        foreach ($missingAreas as $name => $url) {
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
     * Parse area links from the WorldMap HTML.
     */
    private function parseAreaLinks(string $html): void
    {
        // Find all links in the map content
        // The map is in a <pre> block with HTML wiki links
        if (preg_match('/<pre[^>]*>(.+?)<\/pre>/si', $html, $m)) {
            $mapContent = $m[1];

            // Find all HTML wiki links in the map
            // Pattern matches <a href="http://wiki.qhcf.net/index.php?title=PageName" ...>Display Text</a>
            preg_match_all('/<a[^>]+href="(http:\/\/wiki\.qhcf\.net\/index\.php\?title=[^"]+)"[^>]*>([^<]+)<\/a>/', $mapContent, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $url = $match[1];
                $linkText = trim($match[2]);

                // Clean up the area name
                $areaName = $this->cleanAreaName($linkText);

                // Skip very short names (likely single letters or connectors)
                if (strlen($areaName) < 3) {
                    continue;
                }

                // Skip common connector words and partial matches
                $skipWords = ['the', 'and', 'of', 'to', 'in', 'a', 'an', 's', 't', 'r', 'd', 'g', 'l', 'm', 'n', 'k', 'i', 'o', 'e', 'w', 'h', 'c', 'u', 'p', 'v', 'f', 'b', 'th', 'st', 'rd', 'nd'];
                if (in_array(strtolower($areaName), $skipWords)) {
                    continue;
                }

                // Skip if it's just a partial word fragment
                if (preg_match('/^[a-z]{1,2}$/i', $areaName)) {
                    continue;
                }

                // Skip author names
                $skipNames = ['RobertDunn', 'Zendrac', 'Yhorian', 'DurNominator', 'Robert', 'Dunn', 'Zendra', 'Yhoria'];
                if (in_array($areaName, $skipNames)) {
                    continue;
                }

                // Store the area
                if (!isset($this->mapAreaLinks[$areaName])) {
                    $this->mapAreaLinks[$areaName] = $url;
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
