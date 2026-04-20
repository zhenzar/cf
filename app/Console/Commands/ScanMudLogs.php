<?php

namespace App\Console\Commands;

use App\Services\LogScanner;
use Illuminate\Console\Command;

class ScanMudLogs extends Command
{
    protected $signature = 'mudlogs:scan {path : Absolute path to directory}';
    protected $description = 'Recursively scan a directory for .txt mudlogs and ingest items.';

    public function handle(LogScanner $scanner): int
    {
        $path = $this->argument('path');
        $this->info("Scanning {$path} ...");
        try {
            $summary = $scanner->scanDirectory($path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Files seen: %d, new/updated: %d, items ingested: %d',
            $summary['filesSeen'], $summary['filesNew'], $summary['itemsNew']
        ));
        return self::SUCCESS;
    }
}
