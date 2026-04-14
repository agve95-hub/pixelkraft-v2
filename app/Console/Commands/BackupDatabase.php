<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'pixelkraft:backup-database';

    protected $description = 'Backup the MariaDB database to Cloudflare R2';

    public function handle(): int
    {
        $dbName = config('database.connections.mariadb.database', config('database.connections.mysql.database'));
        $dbUser = config('database.connections.mariadb.username', config('database.connections.mysql.username'));
        $dbPass = config('database.connections.mariadb.password', config('database.connections.mysql.password'));
        $dbHost = config('database.connections.mariadb.host', config('database.connections.mysql.host', '127.0.0.1'));

        $timestamp = now()->format('Y-m-d_His');
        $filename = "pixelkraft_{$timestamp}.sql.gz";
        $localPath = storage_path("app/backups/{$filename}");

        // Ensure local backup directory exists
        if (! is_dir(dirname($localPath))) {
            mkdir(dirname($localPath), 0755, true);
        }

        // Dump database
        $result = Process::timeout(300)->run(
            "mysqldump -h {$dbHost} -u {$dbUser}".
            ($dbPass ? ' -p'.escapeshellarg($dbPass) : '').
            " {$dbName} | gzip > ".escapeshellarg($localPath)
        );

        if (! $result->successful() || ! file_exists($localPath)) {
            $this->error('Database dump failed: '.$result->errorOutput());

            return self::FAILURE;
        }

        $size = filesize($localPath);
        $this->info("Database dumped: {$filename} (".number_format($size / 1024 / 1024, 2).' MB)');

        // Upload to R2
        $disk = config('pixelkraft.backup.disk', 'r2');

        try {
            Storage::disk($disk)->put(
                "backups/db/{$filename}",
                file_get_contents($localPath)
            );

            $this->info("Uploaded to {$disk}: backups/db/{$filename}");
        } catch (\Throwable $e) {
            $this->warn("R2 upload failed: {$e->getMessage()}. Backup retained locally.");
        }

        // Clean up old local backups (keep 7 days)
        $this->cleanLocalBackups(7);

        // Clean up old remote backups
        $this->cleanRemoteBackups($disk, config('pixelkraft.backup.retention_days', 30));

        // Remove local file after upload
        @unlink($localPath);

        return self::SUCCESS;
    }

    private function cleanLocalBackups(int $retainDays): void
    {
        $dir = storage_path('app/backups');

        if (! is_dir($dir)) {
            return;
        }

        $cutoff = now()->subDays($retainDays)->timestamp;

        foreach (glob("{$dir}/*.sql.gz") as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    private function cleanRemoteBackups(string $disk, int $retainDays): void
    {
        try {
            $files = Storage::disk($disk)->files('backups/db');
            $cutoff = now()->subDays($retainDays);

            foreach ($files as $file) {
                $modified = Storage::disk($disk)->lastModified($file);

                if ($modified < $cutoff->timestamp) {
                    Storage::disk($disk)->delete($file);
                }
            }
        } catch (\Throwable $e) {
            // Silent fail — don't break backup over cleanup issues
        }
    }
}
