<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Snapshots the SQLite database file to storage/app/backups. This is a
 * pure file copy (no artisan migrate/seed involved) so it can never itself
 * cause data loss — it only ever adds a new timestamped file.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--keep=30 : Number of most recent backups to retain}';

    protected $description = 'Back up the SQLite database file to storage/app/backups';

    public function handle(): int
    {
        $source = database_path('database.sqlite');

        if (! File::exists($source)) {
            $this->error("No database file found at {$source}.");

            return self::FAILURE;
        }

        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $destination = $backupDir.'/database_'.now()->format('Ymd_His').'.sqlite';
        File::copy($source, $destination);

        $this->info("Backed up database to {$destination}");

        $keep = (int) $this->option('keep');
        $backups = collect(File::files($backupDir))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), 'database_'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        $backups->slice($keep)->each(function ($file) {
            File::delete($file->getPathname());
            $this->line("Pruned old backup: {$file->getFilename()}");
        });

        return self::SUCCESS;
    }
}
