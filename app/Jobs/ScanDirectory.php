<?php

namespace App\Jobs;

use App\Models\LogFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Finder\Finder;

class ScanDirectory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public string $dir) {}

    public function handle(): void
    {
        if (! is_dir($this->dir)) {
            return;
        }

        $finder = (new Finder())->files()->in($this->dir)->name('*.txt');
        foreach ($finder as $file) {
            $path = $file->getRealPath();
            $existing = LogFile::where('path', $path)->first();
            if ($existing && $existing->scanned_at && $existing->size === $file->getSize()) {
                continue; // unchanged, skip
            }

            IngestLogFile::dispatch(
                $path,
                $file->getFilename(),
                'scan',
                $file->getSize(),
            );
        }
    }
}
