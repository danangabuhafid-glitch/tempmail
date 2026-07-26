<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Backup database + lampiran + raw email ke satu arsip ZIP di disk privat,
 * dengan rotasi otomatis (simpan N terakhir).
 */
class BackupCommand extends Command
{
    protected $signature = 'tempmail:backup {--keep=7 : Jumlah arsip terakhir yang disimpan}';

    protected $description = 'Backup database + file email ke storage/app/backups';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');

        $stamp = now()->format('Ymd-His');
        $zipPath = $disk->path("backups/tempmail-{$stamp}.zip");

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->error('Tidak bisa membuat file ZIP backup.');
            return self::FAILURE;
        }

        // 1. Dump database (mysqldump untuk MySQL, salin file untuk SQLite)
        try {
            $dump = $this->dumpDatabase();
        } catch (\Throwable $e) {
            $this->error('Dump database gagal: ' . $e->getMessage());
            $dump = null;
        }

        if ($dump === null) {
            $zip->close();
            @unlink($zipPath);
            return self::FAILURE;
        }
        $zip->addFromString('database.sql', $dump);

        // 2. File lampiran + raw .eml
        foreach (['attachments', 'raw'] as $dir) {
            foreach ($disk->allFiles($dir) as $file) {
                $zip->addFile($disk->path($file), $file);
            }
        }

        $zip->close();

        // 3. Rotasi: hanya simpan N arsip terbaru
        $keep = max(1, (int) $this->option('keep'));
        $arsip = collect($disk->files('backups'))
            ->filter(fn ($f) => str_ends_with($f, '.zip'))
            ->sortDesc()
            ->values();
        $arsip->slice($keep)->each(fn ($f) => $disk->delete($f));

        Setting::setValue('backup_last_run', now()->toIso8601String());
        Setting::setValue('backup_last_file', "backups/tempmail-{$stamp}.zip");

        $this->info("Backup selesai: backups/tempmail-{$stamp}.zip (" . number_format(filesize($zipPath) / 1048576, 1) . ' MB)');

        return self::SUCCESS;
    }

    private function dumpDatabase(): ?string
    {
        $conn = config('database.default');
        $cfg = config("database.connections.{$conn}");

        if ($cfg['driver'] === 'sqlite') {
            $path = $cfg['database'];
            if ($path === ':memory:' || !is_file($path)) {
                return "-- database sqlite in-memory, tidak ada yang di-dump\n";
            }
            return file_get_contents($path) ?: null;
        }

        if ($cfg['driver'] === 'mysql') {
            // XAMPP menyimpan mysqldump di folder bin-nya sendiri
            $bin = is_file('/Applications/XAMPP/xamppfiles/bin/mysqldump')
                ? '/Applications/XAMPP/xamppfiles/bin/mysqldump'
                : 'mysqldump';

            // Tanpa timeout: dump database besar bisa lewat dari batas default 60 detik
            $result = Process::timeout(0)->env(['MYSQL_PWD' => (string) $cfg['password']])->run([
                $bin,
                '--host=' . $cfg['host'],
                '--port=' . (string) $cfg['port'],
                '--user=' . $cfg['username'],
                '--single-transaction',
                '--skip-lock-tables',
                $cfg['database'],
            ]);

            if (!$result->successful()) {
                $this->error('mysqldump gagal: ' . $result->errorOutput());
                return null;
            }

            return $result->output();
        }

        $this->error("Driver {$cfg['driver']} belum didukung backup.");
        return null;
    }
}
