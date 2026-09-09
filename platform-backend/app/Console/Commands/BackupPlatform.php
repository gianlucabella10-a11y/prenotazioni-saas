<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\ControlRoom\Application\CreatePlatformBackup;
use Illuminate\Console\Command;

/**
 * Backup del centro operativo da terminale (equivalente CLI del pulsante
 * "Backup ora" in Control Room — stessa classe, `CreatePlatformBackup`).
 * Pensato anche per l'esecuzione schedulata (AUTOMATION_CATALOG.md #1).
 */
final class BackupPlatform extends Command
{
    protected $signature = 'platform:backup';

    protected $description = 'Crea un backup di database (sqlite) + storage/app in <repo>/backups/';

    public function handle(CreatePlatformBackup $backup): int
    {
        $result = $backup->execute();

        $this->info("Backup creato: {$result['filename']} (".number_format($result['size_bytes'] / 1024, 1).' KB)');

        if ($result['pruned'] > 0) {
            $this->line("Rimossi {$result['pruned']} backup più vecchi (retention automatica).");
        }

        if (! $result['database_included']) {
            $this->warn('Database non incluso (connessione non sqlite) — vedi docs/Operations/BACKUP_RECOVERY_GUIDE.md per mysqldump/pg_dump.');
        }

        return self::SUCCESS;
    }
}
