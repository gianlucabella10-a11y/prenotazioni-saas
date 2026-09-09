<?php

declare(strict_types=1);

namespace App\Modules\ControlRoom\Http\Middleware;

use App\Foundation\Enums\UserType;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cancello della Control Room: solo super_admin autenticati sulla guard
 * `admin`. Un tenant_admin/staff/customer — anche se loggato sul dashboard
 * cliente (guard `web`) — NON entra. Verifica ridondante rispetto al login
 * (difesa in profondità contro privilege escalation).
 */
final class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin');

        if (! $user instanceof User || $user->type !== UserType::SuperAdmin) {
            abort(403, 'Area riservata al proprietario della piattaforma.');
        }

        return $next($request);
    }
}
