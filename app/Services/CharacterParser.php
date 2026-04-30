<?php

namespace App\Services;

class CharacterParser
{
    /**
     * Extract character names from log content (who lists).
     * Returns array of character data.
     */
    public function parseCharacters(string $content): array
    {
        $lines = preg_split('/\r?\n/', $content);
        // Strip leading timestamp
        $stripped = array_map(fn ($l) => preg_replace('/^\d{2}:\d{2}:\d{2}\.\d{3}\s?/', '', $l), $lines);
        // Strip MUD prompt prefix
        $stripped = array_map(fn ($l) => preg_replace('/^\S+\s+<[^>]+>\s*/', '', $l), $stripped);

        $results = [];

        foreach ($stripped as $line) {
            // Pattern: [LEVEL RACE CLASS] (PK) [TITLE] Name the Title
            // Examples:
            // *37 D-Elf War* (PK) [OUTLANDER] Llortisana the Battle-Scarred
            // [34 D-Elf War] (PK) Vynzyr the Warrior of the Shield
            // [51 Storm War] Thrakal the Legend of the Battlefield
            // [37 Felar Asn] Auzlohn the Harai Goshi
            // [ 1 Svirf War] Fettle the Scrapper
            // [51 Dwarf War] (PK) [TRIBUNAL] Banduain Stonefeet ...

            // Capture first capitalized word after optional (PK) and [TITLE] blocks
            if (preg_match('/\[\s*(\d+)\s+([A-Za-z\-]+)\s+([A-Za-z]+)\s*\](?:\s*\(PK\))?(?:\s*\[[^\]]+\])?\s+([A-Z][a-z]+)/', $line, $m)) {
                $name = trim($m[4]);
                if ($name && strlen($name) > 1) {
                    $results[] = [
                        'name' => $name,
                        'level' => (int) $m[1],
                        'race' => trim($m[2]),
                        'class' => trim($m[3]),
                        'source_line' => trim($line),
                    ];
                }
            }

            // Alternative: Star format *37 D-Elf War*
            if (preg_match('/\*\s*(\d+)\s+([A-Za-z\-]+)\s+([A-Za-z]+)\s*\*(?:\s*\(PK\))?(?:\s*\[[^\]]+\])?\s+([A-Z][a-z]+)/', $line, $m)) {
                $name = trim($m[4]);
                if ($name && strlen($name) > 1) {
                    // Check if we already found this name
                    $exists = false;
                    foreach ($results as $r) {
                        if ($r['name'] === $name) {
                            $exists = true;
                            break;
                        }
                    }
                    if (! $exists) {
                        $results[] = [
                            'name' => $name,
                            'level' => (int) $m[1],
                            'race' => trim($m[2]),
                            'class' => trim($m[3]),
                            'source_line' => trim($line),
                        ];
                    }
                }
            }
        }

        return $results;
    }
}
