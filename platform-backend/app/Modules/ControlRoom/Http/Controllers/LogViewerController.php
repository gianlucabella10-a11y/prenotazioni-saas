<?php

declare(strict_types=1);

namespace App\Modules\ControlRoom\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Lettura di sola-lettura delle ultime righe di `storage/logs/laravel.log`
 * dalla Control Room — elimina l'ultima operazione diagnostica che richiedeva
 * ancora accesso diretto al filesystem del server (FOUNDER_EXPERIENCE_AUDIT.md,
 * "prossimo collo di bottiglia" già identificato nella sessione precedente).
 * Nessuna scrittura, nessuna nuova tabella: legge il file di log esistente.
 */
final class LogViewerController extends Controller
{
    private const int MAX_LINES = 300;

    public function index(): View
    {
        $path = storage_path('logs/laravel.log');

        if (! is_file($path)) {
            return view('control_room.logs.index', ['lines' => [], 'available' => false, 'sizeBytes' => 0]);
        }

        $lines = $this->tail($path, self::MAX_LINES);

        return view('control_room.logs.index', [
            'lines' => $lines,
            'available' => true,
            'sizeBytes' => filesize($path) ?: 0,
        ]);
    }

    /** @return list<string> */
    private function tail(string $path, int $maxLines): array
    {
        // File di log applicativi: potenzialmente grandi, non caricare tutto in
        // memoria — legge solo l'ultimo blocco (fino a 512KB) e ne estrae le
        // ultime righe complete.
        $maxBytes = 512 * 1024;
        $size = filesize($path) ?: 0;
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $offset = max(0, $size - $maxBytes);
        fseek($handle, $offset);
        $chunk = stream_get_contents($handle) ?: '';
        fclose($handle);

        $lines = preg_split('/\R/', trim($chunk)) ?: [];

        if ($offset > 0 && $lines !== []) {
            array_shift($lines); // prima riga probabilmente troncata
        }

        return array_slice($lines, -$maxLines);
    }
}
