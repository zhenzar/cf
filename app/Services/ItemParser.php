<?php

namespace App\Services;

class ItemParser
{
    /**
     * Extract all identification blocks from a full log text.
     * Returns array of parsed item data (one per block).
     */
    public function parseFile(string $content): array
    {
        $lines = preg_split('/\r?\n/', $content);
        // Strip leading timestamp like "14:14:57.865 " from each line.
        $stripped = array_map(fn ($l) => preg_replace('/^\d{2}:\d{2}:\d{2}\.\d{3}\s?/', '', $l), $lines);

        $results = [];
        $n = count($stripped);
        $dividerRe = '/^-{20,}\s*$/';

        for ($i = 0; $i < $n; $i++) {
            $line = $stripped[$i];
            // Trigger: "You furrow your brow..." then a divider on next line.
            if (stripos($line, 'furrow your brow') !== false) {
                // Find opening divider within the next few lines
                $start = null;
                for ($j = $i + 1; $j < min($i + 4, $n); $j++) {
                    if (preg_match($dividerRe, $stripped[$j])) {
                        $start = $j;
                        break;
                    }
                }
                if ($start === null) continue;

                // Find closing divider
                $end = null;
                for ($j = $start + 1; $j < min($start + 60, $n); $j++) {
                    if (preg_match($dividerRe, $stripped[$j])) {
                        $end = $j;
                        break;
                    }
                }
                if ($end === null) continue;

                $block = array_slice($stripped, $start + 1, $end - $start - 1);
                $parsed = $this->parseBlock($block);
                if ($parsed) {
                    $results[] = $parsed;
                }
                $i = $end;
            }
        }

        return $results;
    }

    /**
     * Parse a single identification block (lines between dividers, timestamps stripped).
     */
    public function parseBlock(array $lines): ?array
    {
        // Merge continuation lines (lines starting with 2+ spaces) into the previous.
        $merged = [];
        foreach ($lines as $l) {
            if ($l === '' || trim($l) === '') continue;
            if (preg_match('/^\s{2,}/', $l) && !empty($merged)) {
                $merged[count($merged) - 1] .= ' ' . trim($l);
            } else {
                $merged[] = rtrim($l);
            }
        }

        if (empty($merged)) return null;

        $data = [
            'name' => null,
            'keyword' => null,
            'worth_copper' => null,
            'level' => null,
            'item_type' => null,
            'slot' => null,
            'material' => null,
            'weight_pounds' => null,
            'weight_ounces' => null,
            'weapon_class' => null,
            'weapon_qualifier' => null,
            'damage_type' => null,
            'attack_type' => null,
            'alignment' => null,
            'damage_dice' => null,
            'av_damage' => null,
            'protections' => [],
            'affects' => [],
            'flags' => [],
            'spells' => [],
            'raw_text' => implode("\n", $lines),
        ];

        foreach ($merged as $line) {
            $line = trim($line);

            // Name + keyword: "a X can be referred to as 'keyword'."
            if (preg_match("/^(?:a|an|the|some)\s+(.+?)\s+can be referred to as '([^']+)'/i", $line, $m)) {
                $data['name'] = trim($m[1]);
                $data['keyword'] = trim($m[2]);
                continue;
            }

            // Worth + level: "It is worth N copper, and is of the Nth level of power."
            if (preg_match('/worth\s+([\d,]+)\s+copper.*?(\d+)(?:st|nd|rd|th)\s+level/i', $line, $m)) {
                $data['worth_copper'] = (int) str_replace(',', '', $m[1]);
                $data['level'] = (int) $m[2];
                continue;
            }

            // Explicit miscellaneous type: "It is a miscellaneous object."
            if (preg_match('/^it is an?\s+miscellaneous\s+object/i', $line)) {
                $data['item_type'] = 'Miscellaneous';
                continue;
            }

            // Simple type-only lines: "It is a wand." / "It is a scroll." / "It is a staff." / "It is a potion." / "It is a container." / "It is food." / "It is a drink container."
            if (preg_match('/^it is an?\s+(wand|scroll|staff|potion|container|drink container|key|light|food|trash|pill|money|fountain|armor|clothing|shield|weapon|treasure|instrument)\s*\.?\s*$/i', $line, $m)) {
                $data['item_type'] = ucwords(strtolower($m[1]));
                continue;
            }

            // "It is a thief tool." → Lockpicks
            if (preg_match('/^it is an?\s+thief\s+tool\s*\.?\s*$/i', $line)) {
                $data['item_type'] = 'Lockpicks';
                continue;
            }

            // Wand / staff single spell: "It contains the spell 'X' of the Nth level."
            if (preg_match("/contains the spell\s+'([^']+)'\s+of the\s+(\d+)(?:st|nd|rd|th)\s+level/i", $line, $m)) {
                $data['spells'][] = ['name' => trim($m[1]), 'level' => (int) $m[2]];
                continue;
            }

            // Scroll multi-spell: "Within it are contained level N spells of 'X' and 'Y'"
            // or: "Within it are contained level N spells of 'X', 'Y' and 'Z'"
            if (preg_match("/within it (?:are|is) contained level\s+(\d+)\s+spells?\s+of\s+(.+)$/i", $line, $m)) {
                $level = (int) $m[1];
                if (preg_match_all("/'([^']+)'/", $m[2], $sm)) {
                    foreach ($sm[1] as $name) {
                        $data['spells'][] = ['name' => trim($name), 'level' => $level];
                    }
                }
                continue;
            }

            // Item type + slot: "It is armor worn about the body." / "It is a weapon..."
            if (preg_match('/^It is (armor|clothing|an instrument|a treasure|a weapon|a shield)\b(.*)$/i', $line, $m)) {
                $type = strtolower($m[1]);
                $type = preg_replace('/^(a|an)\s+/', '', $type);
                $data['item_type'] = ucfirst($type);

                $rest = $m[2];
                if (preg_match('/worn (?:about|on|around) the (.+?)\./i', $rest, $sm)) {
                    $data['slot'] = ucfirst(strtolower(trim($sm[1])));
                } elseif (stripos($rest, 'wielded') !== false || $data['item_type'] === 'Weapon') {
                    $data['slot'] = 'Weapon';
                } elseif (stripos($rest, 'held') !== false) {
                    $data['slot'] = 'Hold';
                }
                continue;
            }

            // Material + weight: "It is made of hide and weighs 10 pounds 4 ounces."
            if (preg_match('/made of\s+([a-zA-Z\- ]+?)\s+and weighs\s+(\d+)\s+pounds?(?:\s+(\d+)\s+ounces?)?/i', $line, $m)) {
                $data['material'] = trim($m[1]);
                $data['weight_pounds'] = (int) $m[2];
                $data['weight_ounces'] = isset($m[3]) ? (int) $m[3] : 0;
                continue;
            }

            // Weapon class + damage type: "It is a <class> that inflicts <type> damage."
            if (preg_match('/it is an?\s+(\w+)\s+that inflicts\s+(\w+)\s+damage/i', $line, $m)) {
                $data['weapon_class'] = ucfirst(strtolower($m[1]));
                $data['damage_type'] = ucfirst(strtolower($m[2]));
                $data['item_type'] = $data['item_type'] ?? 'Weapon';
                $data['slot'] = $data['slot'] ?? 'Wield';
                continue;
            }

            // Weapon line w/ attack type: "It is a two-handed axe with an attack type of crush."
            // Qualifier is optional.
            if (preg_match('/^it is an?\s+(?:(two-handed|one-handed|dual-wielded)\s+)?([a-z]+)\s+with an attack type of\s+(\w+)/i', $line, $m)) {
                if (!empty($m[1])) $data['weapon_qualifier'] = strtolower($m[1]);
                $data['weapon_class'] = ucfirst(strtolower($m[2]));
                $data['attack_type'] = strtolower($m[3]);
                $data['item_type'] = $data['item_type'] ?? 'Weapon';
                $data['slot'] = $data['slot'] ?? 'Wield';
                continue;
            }

            // Damage dice + average: "It can cause 3d19 points of damage, at average 30."
            if (preg_match('/(?:can cause|does|inflicts)\s+(\d+d\d+)\s+points? of damage(?:,?\s+at average\s+(\d+(?:\.\d+)?))?/i', $line, $m)) {
                $data['damage_dice'] = strtolower($m[1]);
                if (!empty($m[2])) $data['av_damage'] = $m[2];
                continue;
            }

            // Fallback average damage: "average damage is N"
            if (preg_match('/average\s+damage\s+(?:is|of)?\s*(\d+(?:\.\d+)?)/i', $line, $m)) {
                $data['av_damage'] = $m[1];
                continue;
            }

            // Protections: "When worn, it protects you against piercing for 10, bashing for 6,
            //   slashing for 10, magic for 2, and the elements for 9 points each."
            if (stripos($line, 'protects you against') !== false) {
                if (preg_match_all('/(piercing|bashing|slashing|magic|elements?|acid|cold|fire|lightning|mental|holy|light|negative|disease|poison|drowning|energy)\s+for\s+(-?\d+)/i', $line, $pm, PREG_SET_ORDER)) {
                    foreach ($pm as $p) {
                        $data['protections'][] = [
                            'type' => ucfirst(strtolower(rtrim($p[1], 's'))),
                            'value' => (int) $p[2],
                        ];
                    }
                }
                continue;
            }

            // Affects: "When worn, it affects your X by N points, your Y by M points..."
            if (preg_match('/affects your /i', $line)) {
                if (preg_match_all('/(?:affects\s+)?your\s+(.+?)\s+by\s+(-?\d+)\s+points?/i', $line, $am, PREG_SET_ORDER)) {
                    foreach ($am as $a) {
                        $stat = trim($a[1]);
                        // Clean trailing conjunctions
                        $stat = preg_replace('/\s+(and|,)\s*$/i', '', $stat);
                        $data['affects'][] = [
                            'stat' => $stat,
                            'modifier' => (int) $a[2],
                        ];
                    }
                }
                continue;
            }

            // Flags: "It is flagged as X, Y, Z." or "It has the following flags: X, Y."
            if (preg_match('/(?:flagged as|has the following flags?:?)\s*(.+?)\.?$/i', $line, $m)) {
                $flags = preg_split('/[,;]|\s+and\s+/i', $m[1]);
                foreach ($flags as $f) {
                    $f = trim($f);
                    if ($f !== '') {
                        $data['flags'][] = strtolower($f);
                    }
                }
                continue;
            }

            // Well-known descriptive flag lines.
            if (stripos($line, 'imbued with a blessing') !== false) {
                $data['flags'][] = 'blessed';
                continue;
            }
            if (preg_match('/magical aura surrounds it/i', $line)) {
                $data['flags'][] = 'magical';
                continue;
            }
            if (preg_match('/chilling aura of evil/i', $line)) {
                $data['flags'][] = 'evil';
                continue;
            }
            if (preg_match('/warm aura of good/i', $line)) {
                $data['flags'][] = 'good';
                continue;
            }

            // Alignment restrictions (what CANNOT use it).
            // "It is unusable for those of a pure soul." → -G (good cannot use it)
            if (preg_match('/unusable for those of a pure soul/i', $line)
                || preg_match('/pure soul cannot (?:use|wield) it/i', $line)) {
                $data['alignment'] = ($data['alignment'] ?? '') . '-G';
                continue;
            }
            // "It is unusable for those of a corrupt/dark soul." → -E
            // Also: "People of a dark heart cannot use it."
            if (preg_match('/unusable for those of (?:a\s+)?(?:corrupt|dark|evil)\s+soul/i', $line)
                || preg_match('/(?:corrupt|dark|evil) soul cannot (?:use|wield) it/i', $line)
                || preg_match('/people of (?:a\s+)?(?:dark|corrupt|evil)\s+heart cannot (?:use|wield) it/i', $line)) {
                $data['alignment'] = ($data['alignment'] ?? '') . '-E';
                continue;
            }
            // "People of a pure heart cannot use it." → -G (mirror)
            if (preg_match('/people of (?:a\s+)?(?:pure|good|light)\s+heart cannot (?:use|wield) it/i', $line)) {
                $data['alignment'] = ($data['alignment'] ?? '') . '-G';
                continue;
            }

            // Class / race / size restriction: "Only an Outlander of Thar'Eris could use it."
            //                                   "It is clearly meant for a giant."
            // Stored as a flag like "outlander only" / "giant only".
            if (preg_match('/^only (?:an?\s+)?([A-Z][A-Za-z\-]+)(?:\s+of\s+[A-Za-z\'\-]+)?\s+could\s+(?:use|wield|wear)\s+it/i', $line, $m)) {
                $data['flags'][] = strtolower($m[1]) . ' only';
                continue;
            }
            if (preg_match('/^it is (?:clearly\s+)?(?:meant|made|designed|intended)\s+for\s+(?:an?\s+)?([A-Za-z\-]+)/i', $line, $m)) {
                $data['flags'][] = strtolower($m[1]) . ' only';
                continue;
            }
            // "Those with a balanced soul cannot use it." → -N
            if (preg_match('/(?:those with|for those of)\s+a\s+balanced\s+soul\s+cannot use it/i', $line)
                || preg_match('/balanced soul cannot (?:use|wield) it/i', $line)) {
                $data['alignment'] = ($data['alignment'] ?? '') . '-N';
                continue;
            }
            if (preg_match('/^it (?:glows|emanates|radiates|hums|emits|is humming|is glowing)\b/i', $line, $m)) {
                // Normalize glow/hum-style lines into simple flags (e.g. "glows", "hums", "emanates sound").
                $flag = strtolower(preg_replace('/[.\s]+$/', '', trim(substr($line, 3))));
                $flag = preg_replace('/^is\s+/', '', $flag);
                if ($flag !== '') $data['flags'][] = $flag;
                continue;
            }
        }

        // Promote weapon qualifier (two-handed/one-handed/dual-wielded) into a flag.
        if (!empty($data['weapon_qualifier'])) {
            $data['flags'][] = $data['weapon_qualifier'];
        }

        // Dedup flags.
        $data['flags'] = array_values(array_unique($data['flags']));

        // Require at least a name to be considered valid.
        if (! $data['name']) {
            return null;
        }

        return $data;
    }
}
