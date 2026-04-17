<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('areas.md');
        if (! is_file($path)) {
            $this->command?->warn("areas.md not found at {$path}, skipping.");
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $seen = [];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line));
            // Expected format: '', 'LEVEL', 'REALM - NAME', possibly empty trailing
            $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));
            if (count($parts) < 2) {
                continue;
            }

            [$levelPart, $rest] = [$parts[0], $parts[1]];

            if (strcasecmp($levelPart, 'All') === 0) {
                $min = 1;
                $max = 51;
            } elseif (preg_match('/^(\d+)\s*-\s*(\d+)$/', $levelPart, $m)) {
                $min = (int) $m[1];
                $max = (int) $m[2];
            } else {
                continue;
            }

            if (! str_contains($rest, ' - ')) {
                continue;
            }
            [$realm, $nameAndUrl] = array_map('trim', explode(' - ', $rest, 2));

            // Extract optional trailing URL marker: "Name   #https://..."
            $url = null;
            if (preg_match('/^(.*?)\s*#\s*(https?:\/\/\S+)\s*$/', $nameAndUrl, $m)) {
                $nameAndUrl = trim($m[1]);
                $url = $m[2];
            }
            $name = rtrim($nameAndUrl, ". \t");

            $area = Area::updateOrCreate(
                ['realm' => $realm, 'name' => $name],
                ['min_level' => $min, 'max_level' => $max, 'url' => $url],
            );
            $seen[] = $area->id;
        }

        // Remove areas no longer present in the file. Also detach any
        // character completions for those areas.
        $stale = Area::whereNotIn('id', $seen)
            // Only prune areas originally sourced from areas.md (not boats etc.).
            ->whereNotIn('realm', ['Merchants Dock', 'Hidden Dock', 'South Dock', 'North Dock'])
            ->pluck('id');

        if ($stale->isNotEmpty()) {
            \DB::table('area_character')->whereIn('area_id', $stale)->delete();
            Area::whereIn('id', $stale)->delete();
            $this->command?->info("Removed {$stale->count()} stale areas.");
        }
    }
}
