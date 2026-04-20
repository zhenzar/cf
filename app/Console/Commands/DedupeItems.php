<?php

namespace App\Console\Commands;

use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupeItems extends Command
{
    protected $signature = 'mudlogs:dedupe {--dry-run : Only show what would change}';
    protected $description = 'Backfill stats_hash on all items and remove exact duplicates.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->info('Backfilling stats_hash ...');
        $backfilled = 0;
        Item::with(['protections', 'affects', 'flags'])->chunkById(200, function ($items) use (&$backfilled, $dry) {
            foreach ($items as $item) {
                $h = $item->computeStatsHash();
                if ($item->stats_hash !== $h) {
                    if (! $dry) {
                        $item->forceFill(['stats_hash' => $h])->saveQuietly();
                    }
                    $backfilled++;
                }
            }
        });
        $this->info("Backfilled: {$backfilled}");

        $this->info('Scanning for duplicate stats_hash ...');
        // Find stats_hash values with more than one item.
        $dupHashes = DB::table('items')
            ->select('stats_hash', DB::raw('COUNT(*) as c'))
            ->whereNotNull('stats_hash')
            ->groupBy('stats_hash')
            ->having('c', '>', 1)
            ->pluck('stats_hash');

        $this->info("Duplicate hashes: {$dupHashes->count()}");

        $deleted = 0;
        foreach ($dupHashes as $hash) {
            // Keep the oldest confirmed item (lowest id, status confirmed preferred).
            $group = Item::where('stats_hash', $hash)
                ->orderByRaw("CASE WHEN status = 'confirmed' THEN 0 ELSE 1 END")
                ->orderBy('id')
                ->get();
            $keep = $group->first();
            foreach ($group->slice(1) as $dup) {
                if (! $dry) {
                    $dup->delete();
                }
                $deleted++;
            }
            $this->line("  kept #{$keep->id} '{$keep->name}', removed " . ($group->count() - 1));
        }

        $this->info("Removed: {$deleted}" . ($dry ? ' (dry run)' : ''));
        return self::SUCCESS;
    }
}
