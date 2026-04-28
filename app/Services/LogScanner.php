<?php

namespace App\Services;

use App\Models\Item;
use App\Models\LogFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;

class LogScanner
{
    /**
     * Flags that should be ignored when computing the dedup hash. These
     * can vary between otherwise-identical item identifications without
     * meaningfully changing the item (e.g. a blessed/unblessed copy).
     */
    public const FLAGS_IGNORED_FOR_DEDUP = ['blessed', 'magical'];

    public function __construct(private ItemParser $parser) {}

    /**
     * Recursively scan a directory for .txt files and ingest each.
     * Returns summary [files_seen, files_new, items_new].
     */
    public function scanDirectory(string $dir): array
    {
        if (! is_dir($dir)) {
            throw new \InvalidArgumentException("Not a directory: {$dir}");
        }

        $finder = (new Finder())->files()->in($dir)->name('*.txt');
        $filesSeen = 0;
        $filesNew = 0;
        $itemsNew = 0;

        foreach ($finder as $file) {
            $filesSeen++;
            $path = $file->getRealPath();
            $existing = LogFile::where('path', $path)->first();
            if ($existing && $existing->scanned_at && $existing->size === $file->getSize()) {
                continue; // unchanged, skip
            }

            $res = $this->ingestFile($path, $file->getFilename(), 'scan', $file->getSize());
            if ($res['new_file']) $filesNew++;
            $itemsNew += $res['items_new'];
        }

        return compact('filesSeen', 'filesNew', 'itemsNew');
    }

    /**
     * Read file content, handling both regular and gzipped files.
     */
    private function readFile(string $path): string
    {
        // Check if file is gzipped
        if (str_ends_with($path, '.gz') || str_ends_with($path, '.gzip')) {
            $content = gzfile($path);
            if ($content === false) {
                throw new \RuntimeException("Cannot read gzipped file {$path}");
            }
            return implode('', $content);
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Cannot read {$path}");
        }
        return $content;
    }

    /**
     * Ingest a single file path. Returns ['log_file' => LogFile, 'items_new' => int, 'new_file' => bool].
     */
    public function ingestFile(string $path, string $filename, string $source = 'scan', ?int $size = null): array
    {
        $content = $this->readFile($path);
        $size = $size ?? strlen($content);
        $contentHash = hash('sha256', $content);

        // If another record already holds this exact content, update this path's record
        // to reflect it was processed and link the other's items via the pivot so this
        // file shows up populated instead of empty.
        $existingByHash = LogFile::where('content_hash', $contentHash)
            ->where('path', '!=', $path)
            ->first();
        if ($existingByHash) {
            // Fetch existing record for this path, or create new
            $logFile = LogFile::where('path', $path)->first();
            if (! $logFile) {
                $logFile = new LogFile(['path' => $path]);
            }

            $logFile->filename = $filename;
            $logFile->source = $source;
            $logFile->size = $size;
            $logFile->scanned_at = now();
            $logFile->save();

            foreach ($existingByHash->items()->pluck('items.id') as $iid) {
                $logFile->items()->syncWithoutDetaching([$iid => ['created_at' => now()]]);
            }
            $logFile->items_count = $logFile->items()->count();
            $logFile->reviewed = true;
            $logFile->save();

            return ['log_file' => $logFile, 'items_new' => 0, 'new_file' => false];
        }

        $logFile = LogFile::firstOrNew(['path' => $path]);
        $newFile = ! $logFile->exists;
        $logFile->filename = $filename;
        $logFile->source = $source;
        $logFile->size = $size;
        $logFile->content_hash = $contentHash;
        // Note: content not stored to keep DB small; re-read from disk if needed
        $logFile->scanned_at = now();
        $logFile->save();

        $parsed = $this->parser->parseFile($content);

        $itemsNew = 0;
        DB::transaction(function () use ($parsed, $logFile, &$itemsNew) {
            foreach ($parsed as $data) {
                $hash = hash('sha256', $data['raw_text']);
                $statsHash = $this->computeStatsHashFromData($data);

                // Existing item: attach this log file via pivot (no duplicate record).
                $existing = Item::where('stats_hash', $statsHash)->first();
                if ($existing) {
                    $existing->logFiles()->syncWithoutDetaching([
                        $logFile->id => ['created_at' => now()],
                    ]);
                    continue;
                }

                // Skip Foresight items if one already exists (keep first, ignore subsequent).
                if (stripos($data['name'], 'Foresight') !== false) {
                    if (Item::where('name', $data['name'])->exists()) {
                        continue;
                    }
                }

                // Name already exists but stats differ → queue for review.
                $nameExists = Item::where('name', $data['name'])
                    ->where('status', 'confirmed')->exists();
                $status = $nameExists ? 'pending' : 'confirmed';

                $item = Item::create([
                    'log_file_id' => $logFile->id,
                    'status' => $status,
                    'name' => $data['name'],
                    'keyword' => $data['keyword'],
                    'worth_copper' => $data['worth_copper'],
                    'level' => $data['level'],
                    'item_type' => $data['item_type'],
                    'slot' => $data['slot'],
                    'material' => $data['material'],
                    'weight_pounds' => $data['weight_pounds'],
                    'weight_ounces' => $data['weight_ounces'],
                    'weapon_class' => $data['weapon_class'],
                    'weapon_qualifier' => $data['weapon_qualifier'],
                    'damage_type' => $data['damage_type'],
                    'attack_type' => $data['attack_type'],
                    'damage_dice' => $data['damage_dice'],
                    'av_damage' => $data['av_damage'],
                    'alignment' => $data['alignment'] ?? null,
                    'raw_text' => $data['raw_text'],
                    'hash' => $hash,
                    'stats_hash' => $statsHash,
                ]);

                foreach ($data['protections'] as $p) {
                    $item->protections()->create($p);
                }
                foreach ($data['affects'] as $a) {
                    $item->affects()->create($a);
                }
                foreach ($data['flags'] as $f) {
                    $item->flags()->create(['flag' => $f]);
                }
                foreach ($data['spells'] ?? [] as $s) {
                    $item->spells()->create($s);
                }
                $item->logFiles()->attach($logFile->id, ['created_at' => now()]);
                $itemsNew++;
            }

            $logFile->items_count = $logFile->items()->count();
            $logFile->reviewed = true;
            $logFile->save();
        });

        return ['log_file' => $logFile, 'items_new' => $itemsNew, 'new_file' => $newFile];
    }

    /**
     * Canonical stats hash computed from parsed data (used before an Item exists).
     * Must match Item::computeStatsHash() output on the persisted model.
     */
    public function computeStatsHashFromData(array $d): string
    {
        $norm = fn ($v) => is_string($v) ? trim(strtolower($v)) : $v;

        $protections = collect($d['protections'] ?? [])
            ->map(fn ($p) => strtolower($p['type']) . ':' . $p['value'])
            ->sort()->values()->all();
        $affects = collect($d['affects'] ?? [])
            ->map(fn ($a) => strtolower($a['stat']) . ':' . $a['modifier'])
            ->sort()->values()->all();
        $flags = collect($d['flags'] ?? [])
            ->map(fn ($f) => strtolower($f))
            ->reject(fn ($f) => in_array($f, self::FLAGS_IGNORED_FOR_DEDUP, true))
            ->sort()->values()->all();
        $spells = collect($d['spells'] ?? [])
            ->map(fn ($s) => strtolower($s['name']) . ':' . ($s['level'] ?? ''))
            ->sort()->values()->all();

        $payload = [
            'name'             => $norm($d['name'] ?? null),
            'keyword'          => $norm($d['keyword'] ?? null),
            'worth_copper'     => $d['worth_copper'] ?? null,
            'level'            => $d['level'] ?? null,
            'item_type'        => $norm($d['item_type'] ?? null),
            'slot'             => $norm($d['slot'] ?? null),
            'material'         => $norm($d['material'] ?? null),
            'weight_pounds'    => $d['weight_pounds'] ?? null,
            'weight_ounces'    => $d['weight_ounces'] ?? null,
            'weapon_class'     => $norm($d['weapon_class'] ?? null),
            'weapon_qualifier' => $norm($d['weapon_qualifier'] ?? null),
            'damage_type'      => $norm($d['damage_type'] ?? null),
            'attack_type'      => $norm($d['attack_type'] ?? null),
            'damage_dice'      => $norm($d['damage_dice'] ?? null),
            'av_damage'        => $d['av_damage'] ?? null,
            'alignment'        => $norm($d['alignment'] ?? null),
            'protections'      => $protections,
            'affects'          => $affects,
            'flags'            => $flags,
            'spells'           => $spells,
        ];

        return hash('sha256', json_encode($payload));
    }
}
