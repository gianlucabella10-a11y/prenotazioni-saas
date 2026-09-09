<?php

declare(strict_types=1);

namespace App\Modules\ControlRoom\Http\Controllers;

use App\Foundation\Audit\AuditLogger;
use App\Modules\ControlRoom\Application\CreatePlatformBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Backup del centro operativo dalla Control Room — elimina l'unica
 * operazione "backup" che oggi richiedeva il terminale
 * (ZERO_MANUAL_OPERATIONS_AUDIT.md). Stessa logica del comando artisan
 * `platform:backup` (CreatePlatformBackup, condivisa).
 */
final class BackupController extends Controller
{
    public function index(): View
    {
        $backupsDir = base_path('../backups');
        $backups = [];

        if (is_dir($backupsDir)) {
            foreach (glob($backupsDir.'/backup-*.zip') ?: [] as $file) {
                $backups[] = [
                    'filename' => basename($file),
                    'size_bytes' => filesize($file) ?: 0,
                    'created_at' => filemtime($file) ?: 0,
                ];
            }
            usort($backups, static fn (array $a, array $b): int => $b['created_at'] <=> $a['created_at']);
        }

        return view('control_room.backup.index', ['backups' => $backups]);
    }

    public function store(Request $request, CreatePlatformBackup $backup, AuditLogger $audit): RedirectResponse
    {
        $result = $backup->execute();

        $audit->log(
            'control_room.backup_created',
            $request->user('admin')->id,
            ['filename' => $result['filename'], 'size_bytes' => $result['size_bytes'], 'database_included' => $result['database_included']],
        );

        $message = "Backup creato: {$result['filename']}.";

        if ($result['pruned'] > 0) {
            $message .= " Rimossi {$result['pruned']} backup più vecchi (retention automatica).";
        }

        if (! $result['database_included']) {
            $message .= ' Database non incluso (connessione non sqlite) — vedi la guida di ripristino.';
        }

        return redirect()->route('control.backup.index')->with('status', $message);
    }
}
