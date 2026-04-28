<?php

namespace App\Services;

class MobParser
{
    /**
     * Extract mob sightings from log content.
     * Returns array of mob data with equipment.
     */
    public function parseMobs(string $content): array
    {
        $lines = preg_split('/\r?\n/', $content);
        // Strip leading timestamp
        $stripped = array_map(fn ($l) => preg_replace('/^\d{2}:\d{2}:\d{2}\.\d{3}\s?/', '', $l), $lines);
        // Strip MUD prompt prefix
        $stripped = array_map(fn ($l) => preg_replace('/^\S+\s+<[^>]+>\s*/', '', $l), $stripped);

        $results = [];
        $n = count($stripped);

        for ($i = 0; $i < $n; $i++) {
            $line = $stripped[$i];

            // Pattern 1: "There are some mobs you look at this is a \"Mob Name\""
            if (preg_match('/there are some mobs you look at this is (?:an?\s+)?\"([^\"]+)\"|you look at (?:an?\s+)?\"([^\"]+)\"|you see (?:an?\s+)?\"([^\"]+)\"|look at (?:an?\s+)?\"([^\"]+)\"/i', $line, $m)) {
                $mobName = $m[1] ?: $m[2] ?: $m[3] ?: $m[4];
                if ($mobName) {
                    $mobData = $this->parseMobBlock($stripped, $i, trim($mobName));
                    if ($mobData) {
                        $results[] = $mobData;
                    }
                }
            }

            // Pattern 2: Direct look output - "A Gadian Knight is here." followed by equipment
            if (preg_match('/^(A|An|The)\s+([A-Z][a-zA-Z\s]+?)\s+(?:is|are)\s+here/i', $line, $m)) {
                $mobName = trim($m[2]);
                $mobData = $this->parseMobBlock($stripped, $i, $mobName);
                if ($mobData) {
                    $results[] = $mobData;
                }
            }

            // Pattern 3: Combat prompt shows mob: "Your pierce devastates a Gadian Knight!"
            if (preg_match('/(?:pierce|slash|bash|charge|whip|punch|kick|backstab|murder)\s+(?:a|an|the)\s+([A-Z][a-zA-Z\s]+)(?:!|\.|\s+)/i', $line, $m)) {
                $mobName = trim($m[1]);
                // Check if this mob already recorded recently
                $alreadyRecorded = false;
                foreach ($results as $existing) {
                    if (strcasecmp($existing['name'], $mobName) === 0) {
                        $alreadyRecorded = true;
                        break;
                    }
                }
                if (! $alreadyRecorded) {
                    $results[] = [
                        'name' => $mobName,
                        'equipment' => [],
                        'source_line' => $line,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Parse equipment block following a mob sighting.
     */
    private function parseMobBlock(array $lines, int $startIndex, string $mobName): ?array
    {
        $equipment = [];
        $n = count($lines);

        // Look for equipment in next 30 lines
        for ($i = $startIndex + 1; $i < min($startIndex + 30, $n); $i++) {
            $line = trim($lines[$i]);

            // Stop if we hit another mob or a prompt pattern
            if (preg_match('/^(A|An|The)\s+[A-Z]/', $line) && !str_starts_with($line, '<')) {
                break;
            }

            // Pattern: <slot keyword> item description
            // e.g., "<mainhand wielded>  (Illusionary) (Glowing) a hand-axe covered in ice"
            // e.g., "<worn on body>      a suit of plate armor"
            // e.g., "<held>              a glowing staff"
            if (preg_match('/^<([^>]+)>\s+(.+)$/i', $line, $m)) {
                $slotRaw = strtolower(trim($m[1]));
                $itemDesc = trim($m[2]);

                // Normalize slot names
                $slot = $this->normalizeSlot($slotRaw);

                // Skip empty descriptions
                if ($itemDesc && !str_starts_with($itemDesc, '(') && !preg_match('/^\s*$/', $itemDesc)) {
                    $equipment[] = [
                        'slot' => $slot,
                        'item_name' => $itemDesc,
                        'raw' => $line,
                    ];
                }
            }

            // Alternative pattern: "(wielded) (Illusionary) a hand-axe" 
            if (preg_match('/^\(([^)]+)\)\s+(.+)$/i', $line, $m)) {
                $slotRaw = strtolower(trim($m[1]));
                $itemDesc = trim($m[2]);
                $slot = $this->normalizeSlot($slotRaw);

                if ($itemDesc && !preg_match('/^\s*$/', $itemDesc)) {
                    $equipment[] = [
                        'slot' => $slot,
                        'item_name' => $itemDesc,
                        'raw' => $line,
                    ];
                }
            }

            // Stop if we hit common end markers
            if (preg_match('/^(You are carrying:|Inventory:|Items:|-----)/i', $line)) {
                break;
            }
        }

        return [
            'name' => $mobName,
            'equipment' => $equipment,
            'source_line' => $lines[$startIndex] ?? '',
        ];
    }

    /**
     * Normalize slot names to standard format.
     */
    private function normalizeSlot(string $slot): string
    {
        $slot = strtolower(trim($slot));

        // Map various slot formats
        $mappings = [
            'mainhand' => 'mainhand',
            'wield' => 'mainhand',
            'wielded' => 'mainhand',
            'offhand' => 'offhand',
            'secondary' => 'offhand',
            'held' => 'hold',
            'floating' => 'floating',
            'head' => 'head',
            'face' => 'face',
            'neck' => 'neck',
            'body' => 'body',
            'torso' => 'body',
            'arms' => 'arms',
            'hands' => 'hands',
            'waist' => 'waist',
            'legs' => 'legs',
            'feet' => 'feet',
            'shield' => 'shield',
        ];

        // Check for "worn on X" pattern
        if (preg_match('/worn on (\w+)/', $slot, $m)) {
            return $mappings[$m[1]] ?? $m[1];
        }

        // Check for "worn as X" pattern
        if (preg_match('/worn as (\w+)/', $slot, $m)) {
            return $mappings[$m[1]] ?? $m[1];
        }

        // Check for "worn about X" pattern
        if (preg_match('/worn about (\w+)/', $slot, $m)) {
            return $mappings[$m[1]] ?? $m[1];
        }

        // Direct match
        if (isset($mappings[$slot])) {
            return $mappings[$slot];
        }

        return $slot;
    }
}
