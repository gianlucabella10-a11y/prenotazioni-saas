<?php

declare(strict_types=1);

namespace App\Modules\ControlRoom\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Vista di sola lettura sul registro `audit_logs`, oggi scritto da ~20 punti
 * del codice ma consultabile solo via query diretta al DB — colma il gap
 * "cronologia/audit log" segnalato in CONTROL_ROOM_ROADMAP.md come uno dei
 * 3 gap ESSENZIALI. Nessuna scrittura qui: solo lettura, coerente con la
 * convenzione esistente di non audit-loggare le letture.
 */
final class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $action = trim((string) $request->query('action', ''));

        /** @var LengthAwarePaginator $entries */
        $entries = DB::table('audit_logs')
            ->when($action !== '', fn ($query) => $query->where('action', 'like', "%{$action}%"))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('control_room.audit.index', ['entries' => $entries, 'action' => $action]);
    }
}
