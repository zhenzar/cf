<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ScrapeCfItems extends Command
{
    protected $signature = 'scrape:cf-items {--dry-run : Preview only, do not save} {--force : Update existing items}';
    protected $description = 'Scrape items from Carrion Fields website and import to database';

    private array $areaMapping = [];

    public function handle(): int
    {
        $this->info('Fetching item data from Carrion Fields...');

        // Build area name -> id mapping
        $this->areaMapping = Area::pluck('id', 'name')->all();
        $this->info('Loaded ' . count($this->areaMapping) . ' areas from database');

        // Try to get all items by iterating through letters
        $allItems = $this->scrapeAllItems();
        
        $this->info('Total unique items scraped: ' . count($allItems));
        
        // Import items to database
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $unknownAreas = [];
        
        foreach ($allItems as $cfItem) {
            $areaId = null;
            if ($cfItem['area']) {
                $areaId = $this->findAreaId($cfItem['area']);
                if (!$areaId) {
                    $unknownAreas[$cfItem['area']] = true;
                }
            }
            
            $dbItem = Item::where('name', $cfItem['name'])->first();
            
            if (!$dbItem) {
                // Create new item
                if (!$this->option('dry-run')) {
                    $rawText = "Scraped from carrionfields.net: " . $cfItem['name'];
                    $hash = hash('sha256', $rawText);
                    
                    $item = Item::create([
                        'name' => $cfItem['name'],
                        'level' => $cfItem['level'] ?? null,
                        'item_type' => $cfItem['type'] ?? null,
                        'slot' => $cfItem['slot'] ?? null,
                        'material' => $cfItem['material'] ?? null,
                        'worth_copper' => $cfItem['worth'] ?? null,
                        'weight_pounds' => $cfItem['weight_pounds'] ?? null,
                        'weight_ounces' => $cfItem['weight_ounces'] ?? null,
                        'weapon_class' => $cfItem['weapon_class'] ?? null,
                        'av_damage' => $cfItem['av_damage'] ?? null,
                        'damage_type' => $cfItem['damage_type'] ?? null,
                        'area_id' => $areaId,
                        'status' => 'confirmed',
                        'alignment' => $cfItem['align'] ?? null,
                        'raw_text' => $rawText,
                        'hash' => $hash,
                    ]);
                    
                    // Add affects if present
                    if (!empty($cfItem['affects'])) {
                        foreach ($cfItem['affects'] as $affect) {
                            $item->affects()->create($affect);
                        }
                    }
                    
                    // Add protections if present
                    if (!empty($cfItem['protections'])) {
                        foreach ($cfItem['protections'] as $prot) {
                            $item->protections()->create($prot);
                        }
                    }
                    
                    // Add flags if present
                    if (!empty($cfItem['flags'])) {
                        foreach ($cfItem['flags'] as $flag) {
                            $item->flags()->create(['flag' => $flag]);
                        }
                    }
                    
                    // Compute stats hash
                    $item->stats_hash = $item->computeStatsHash();
                    $item->save();
                }
                $created++;
            } elseif ($this->option('force')) {
                // Update existing item
                if (!$this->option('dry-run')) {
                    $dbItem->update([
                        'level' => $cfItem['level'] ?? $dbItem->level,
                        'item_type' => $cfItem['type'] ?? $dbItem->item_type,
                        'slot' => $cfItem['slot'] ?? $dbItem->slot,
                        'material' => $cfItem['material'] ?? $dbItem->material,
                        'worth_copper' => $cfItem['worth'] ?? $dbItem->worth_copper,
                        'weapon_class' => $cfItem['weapon_class'] ?? $dbItem->weapon_class,
                        'av_damage' => $cfItem['av_damage'] ?? $dbItem->av_damage,
                        'damage_type' => $cfItem['damage_type'] ?? $dbItem->damage_type,
                        'area_id' => $areaId ?? $dbItem->area_id,
                        'alignment' => $cfItem['align'] ?? $dbItem->alignment,
                    ]);
                }
                $updated++;
            } else {
                $unchanged++;
            }
        }
        
        $this->info("Created: {$created}, Updated: {$updated}, Unchanged: {$unchanged}");
        
        if (!empty($unknownAreas)) {
            $this->warn('Unknown areas found: ' . count($unknownAreas));
            foreach (array_keys($unknownAreas) as $area) {
                $this->warn("  - {$area}");
            }
        }
        
        return 0;
    }

    private function scrapeAllItems(): array
    {
        $allItems = [];
        
        // The CF item search page shows all items by default with GET request
        $this->info('Fetching all items from CF website...');
        $response = Http::withOptions([
            'verify' => false,
            'timeout' => 120,
        ])->get('https://carrionfields.net/itemsearch/');

        if ($response->successful()) {
            $items = $this->parseItems($response->body());
            $this->info('Found: ' . count($items) . ' items');
            foreach ($items as $item) {
                $allItems[$item['name']] = $item;
            }
        } else {
            $this->error('Failed to fetch items: HTTP ' . $response->status());
        }

        return array_values($allItems);
    }

    private function parseItems(string $html): array
    {
        $items = [];
        
        // Parse item_row divs from the CF website
        // Each item is in <div class="item_row even"> or <div class="item_row odd">
        if (preg_match_all('/<div[^>]*class="item_row[^"]*"[^>]*>(.*?)<\/div>\s*(?=<div[^>]*class="item_row|<\/body|<\/html|$)/si', $html, $matches)) {
            foreach ($matches[1] as $itemHtml) {
                $item = $this->parseItemBlock($itemHtml);
                if ($item && !empty($item['name'])) {
                    $items[] = $item;
                }
            }
        }
        
        return $items;
    }
    
    private function parseItemBlock(string $html): ?array
    {
        $item = [
            'name' => null,
            'area' => null,
            'level' => null,
            'type' => null,
            'slot' => null,
            'material' => null,
            'worth' => null,
            'align' => null,
            'weight_pounds' => null,
            'weight_ounces' => null,
            'affects' => [],
            'protections' => [],
            'flags' => [],
        ];
        
        // Name and Area
        if (preg_match('/<b>Name:<\/b>\s*([^<]+)(?:\s*<b>Area:<\/b>\s*([^<]+))?/i', $html, $m)) {
            $item['name'] = trim(html_entity_decode($m[1]));
            if (isset($m[2])) {
                $item['area'] = trim(html_entity_decode($m[2]));
            }
        }
        
        if (empty($item['name'])) {
            return null;
        }
        
        // Item Type
        if (preg_match('/<b>Item Type:<\/b>\s*([^<\s]+)/i', $html, $m)) {
            $item['type'] = trim($m[1]);
        }
        
        // Wear/Slot
        if (preg_match('/<b>Wear:<\/b>\s*([^<\s]+)/i', $html, $m)) {
            $item['slot'] = ucfirst(strtolower(trim($m[1])));
        }
        
        // Material
        if (preg_match('/<b>Material:<\/b>\s*([^<\s]+)/i', $html, $m)) {
            $item['material'] = trim($m[1]);
        }
        
        // Level
        if (preg_match('/<b>Level:<\/b>\s*(\d+)/i', $html, $m)) {
            $item['level'] = (int)$m[1];
        }
        
        // Weight
        if (preg_match('/<b>Weight:<\/b>\s*(\d+)\s*lb\s*(\d+)\s*oz/i', $html, $m)) {
            $item['weight_pounds'] = (int)$m[1];
            $item['weight_ounces'] = (int)$m[2];
        }
        
        // Flags
        if (preg_match('/<b>Flags:<\/b>\s*([^<]+)/i', $html, $m)) {
            $flags = explode(' ', trim($m[1]));
            $item['flags'] = array_filter($flags);
        }
        
        // Armor Class / Protections
        if (preg_match('/<b>Amor Class:<\/b>\s*([^<]+)/i', $html, $m)) {
            $acText = trim($m[1]);
            // Parse "Pierce: 13 Bash: 7 Slash: 13 Magic: 3 Element: 14"
            if (preg_match_all('/(\w+):\s*(\d+)/i', $acText, $pm, PREG_SET_ORDER)) {
                foreach ($pm as $p) {
                    $item['protections'][] = [
                        'type' => strtolower($p[1]),
                        'value' => (int)$p[2],
                    ];
                }
            }
        }
        
        // Affects - from <ul> with <li>Modifies <span class="affect_name">stat</span> by value
        if (preg_match_all('/<li>\s*Modifies\s*<span[^>]*>([^<]+)<\/span>\s*by\s*([+-]?\d+)/i', $html, $am, PREG_SET_ORDER)) {
            foreach ($am as $a) {
                $item['affects'][] = [
                    'stat' => strtolower(trim($a[1])),
                    'modifier' => (int)$a[2],
                ];
            }
        }
        
        // Weapon Class
        if (preg_match('/<b>Weapon Class:<\/b>\s*([^<\s]+)/i', $html, $m)) {
            $item['weapon_class'] = trim($m[1]);
        }
        
        // Weapon Damage - average value and damage type
        if (preg_match('/<b>Weapon Damage:<\/b>\s*average\s+(\d+)\s*\(([^)]+)\)/i', $html, $m)) {
            $item['av_damage'] = (int)$m[1];
            $item['damage_type'] = trim($m[2]);
        }
        
        return $item;
    }

    private function findAreaId(string $areaName): ?int
    {
        // Exact match
        if (isset($this->areaMapping[$areaName])) {
            return $this->areaMapping[$areaName];
        }

        // Case-insensitive match
        foreach ($this->areaMapping as $name => $id) {
            if (strcasecmp($name, $areaName) === 0) {
                return $id;
            }
        }

        // Partial match - area name contained in search text
        foreach ($this->areaMapping as $name => $id) {
            if (stripos($areaName, $name) !== false || stripos($name, $areaName) !== false) {
                return $id;
            }
        }

        return null;
    }
}
