<?php

namespace App\Services;

use App\Models\Item;
use App\Models\LogFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;

class LogScanner
{
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
     * Ingest a single file path. Returns ['log_file' => LogFile, 'items_new' => int, 'new_file' => bool].
     */
    public function ingestFile(string $path, string $filename, string $source = 'scan', ?int $size = null): array
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Cannot read {$path}");
        }
        $size = $size ?? strlen($content);

        $logFile = LogFile::firstOrNew(['path' => $path]);
        $newFile = ! $logFile->exists;
        $logFile->filename = $filename;
        $logFile->source = $source;
        $logFile->size = $size;
        $logFile->scanned_at = now();
        $logFile->save();

        $parsed = $this->parser->parseFile($content);

        $itemsNew = 0;
        DB::transaction(function () use ($parsed, $logFile, &$itemsNew) {
            foreach ($parsed as $data) {
                $hash = hash('sha256', $data['raw_text']);
                $exists = Item::where('log_file_id', $logFile->id)
                    ->where('hash', $hash)->exists();
                if ($exists) continue;

                $item = Item::create([
                    'log_file_id' => $logFile->id,
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
                    'damage_type' => $data['damage_type'],
                    'av_damage' => $data['av_damage'],
                    'raw_text' => $data['raw_text'],
                    'hash' => $hash,
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
                $itemsNew++;
            }

            $logFile->items_count = $logFile->items()->count();
            $logFile->save();
        });

        return ['log_file' => $logFile, 'items_new' => $itemsNew, 'new_file' => $newFile];
    }
}
