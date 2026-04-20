<?php

namespace App\Jobs;

use App\Services\LogScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IngestLogFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;

    public function __construct(
        public string $path,
        public string $filename,
        public string $source = 'scan',
        public ?int $size = null,
    ) {}

    public function handle(LogScanner $scanner): void
    {
        if (! is_file($this->path)) {
            return;
        }
        $scanner->ingestFile($this->path, $this->filename, $this->source, $this->size);
    }

    public function uniqueId(): string
    {
        return md5($this->path);
    }
}
