<?php

namespace App\Jobs;

use App\Models\Item;
use App\Models\LogFile;
use App\Services\LogScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RescanLogFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;

    public function __construct(
        public int $logFileId,
    ) {}

    public function handle(LogScanner $scanner): void
    {
        $mudlog = LogFile::find($this->logFileId);
        if (! $mudlog) {
            return;
        }

        if (! is_file($mudlog->path)) {
            \Log::warning("RescanLogFile: File not found: {$mudlog->path}");
            return;
        }

        // Detach items and delete orphans within the job
        DB::transaction(function () use ($mudlog) {
            $mudlog->items()->detach();

            $orphanIds = Item::where('log_file_id', $mudlog->id)
                ->whereDoesntHave('logFiles')
                ->pluck('id');
            if ($orphanIds->isNotEmpty()) {
                Item::whereIn('id', $orphanIds)->delete();
            }

            $mudlog->update(['scanned_at' => null, 'items_count' => 0]);
        });

        // Re-ingest the file
        $scanner->ingestFile($mudlog->path, $mudlog->filename, $mudlog->source ?? 'scan');
    }

    public function uniqueId(): string
    {
        return 'rescan-' . $this->logFileId;
    }
}
