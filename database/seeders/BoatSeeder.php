<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;

class BoatSeeder extends Seeder
{
    private const DOCKS = [
        'M' => 'Merchants Dock',
        'H' => 'Hidden Dock',
        'S' => 'South Dock',
        'N' => 'North Dock',
    ];

    public function run(): void
    {
        $path = base_path('boats.md');
        if (! is_file($path)) {
            $this->command?->warn("boats.md not found at {$path}, skipping.");
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $parts = explode("\t", $line);
            if (count($parts) < 3) {
                continue;
            }

            [$dockCode, $boatName, $schedule] = array_map('trim', array_slice($parts, 0, 3));
            $realm = self::DOCKS[strtoupper($dockCode)] ?? null;
            if (! $realm || $boatName === '') {
                continue;
            }

            $name = $schedule !== '' ? "{$boatName} ({$schedule})" : $boatName;

            Area::updateOrCreate(
                ['realm' => $realm, 'name' => $name],
                ['min_level' => 1, 'max_level' => 51],
            );
        }
    }
}
