<?php

declare(strict_types=1);

namespace App\Modules\ControlRoom\Application;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use RuntimeException;
use ZipArchive;

/**
 * Backup del centro operativo: database (se sqlite) + storage/app, in un
 * unico archivio zip sotto `<repo>/backups/`. Stessa cartella di output e
 * stesso contenuto di `platform-infra/bin/backup-control-center.sh`, ma
 * eseguibile senza shell (nessuna dipendenza da `tar`/`cp`) — chiamata sia
 * dal comando artisan `platform:backup` sia dal pulsante "Backup ora" in
 * Control Room (ZERO_MANUAL_OPERATIONS_AUDIT.md).
 *
 * Per MySQL/Postgres il database NON è incluso (nessun dump nativo da PHP
 * puro senza shell): il payload restituito lo segnala esplicitamente,
 * coerente con lo script bash esistente (vedi BACKUP_RECOVERY_GUIDE.md).
 *
 * Retention: ogni chiamata a execute() elimina i backup più vecchi oltre gli
 * ultimi KEEP_BACKUPS — precondizione necessaria per rendere il backup
 * schedulabile in automatico (routes/console.php) senza riempire il disco
 * indefinitamente (PLATFORM_OPERATIONS_MATRIX.md).
 */
final readonly class CreatePlatformBackup
{
    private const int KEEP_BACKUPS = 14;

    public function __construct(private ConfigRepository $config) {}

    /** @return array{filename: string, path: string, size_bytes: int, database_included: bool, pruned: int} */
    public function execute(): array
    {
        $backupsDir = base_path('../backups');

        if (! is_dir($backupsDir) && ! mkdir($backupsDir, 0755, true) && ! is_dir($backupsDir)) {
            throw new RuntimeException("Impossibile creare la cartella di backup: {$backupsDir}");
        }

        $filename = 'backup-'.now()->format('Ymd-His').'.zip';
        $path = $backupsDir.DIRECTORY_SEPARATOR.$filename;

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossibile creare l'archivio di backup: {$path}");
        }

        $databaseIncluded = $this->addDatabase($zip);
        $this->addStorageApp($zip);

        $zip->close();

        return [
            'filename' => $filename,
            'path' => $path,
            'size_bytes' => filesize($path) ?: 0,
            'database_included' => $databaseIncluded,
            'pruned' => $this->pruneOldBackups($backupsDir),
        ];
    }

    /** Elimina i backup più vecchi oltre gli ultimi self::KEEP_BACKUPS. Ritorna quanti ne ha eliminati. */
    private function pruneOldBackups(string $backupsDir): int
    {
        $files = glob($backupsDir.'/backup-*.zip') ?: [];

        if (count($files) <= self::KEEP_BACKUPS) {
            return 0;
        }

        usort($files, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

        $toDelete = array_slice($files, self::KEEP_BACKUPS);
        $deleted = 0;

        foreach ($toDelete as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function addDatabase(ZipArchive $zip): bool
    {
        if ($this->config->get('database.default') !== 'sqlite') {
            return false;
        }

        $sqlitePath = (string) $this->config->get('database.connections.sqlite.database', '');

        if ($sqlitePath === '' || ! is_file($sqlitePath)) {
            return false;
        }

        $zip->addFile($sqlitePath, 'database.sqlite');

        return true;
    }

    private function addStorageApp(ZipArchive $zip): void
    {
        $storageApp = storage_path('app');

        if (! is_dir($storageApp)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($storageApp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $localName = 'storage-app/'.substr((string) $file->getRealPath(), strlen($storageApp) + 1);
            $zip->addFile((string) $file->getRealPath(), $localName);
        }
    }
}
