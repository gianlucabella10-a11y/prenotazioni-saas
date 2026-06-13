<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Middleware;

use App\Foundation\Enums\UserType;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OWNER-only sections (configurazione: servizi, staff, orari, brand).
 * Lo STAFF resta confinato a home e prenotazioni proprie
 * (PROFESSIONAL_DASHBOARD_PLAN §2).
 */
final class RequireOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->type !== UserType::TenantAdmin) {
            abort(403, 'Sezione riservata al titolare.');
        }

        return $next($request);
    }
}
