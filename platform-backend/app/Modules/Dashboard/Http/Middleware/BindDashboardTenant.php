<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Middleware;

use App\Foundation\Enums\UserType;
use App\Foundation\Tenancy\CurrentTenant;
use App\Foundation\Tenancy\TenantRegistry;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboard tenancy gate (docs/28 livello 1, superficie web): il tenant
 * deriva ESCLUSIVAMENTE dall'utente di sessione — mai da input. Utenti
 * senza tenant o senza ruolo gestionale non entrano.
 */
final class BindDashboardTenant
{
    public function __construct(
        private readonly TenantRegistry $registry,
        private readonly CurrentTenant $currentTenant,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User
            || $user->tenant_id === null
            || ! $user->type->canManageTenant()
        ) {
            return redirect()->route('dashboard.login');
        }

        $context = $this->registry->findById($user->tenant_id);

        if ($context === null) {
            abort(403);
        }

        $this->currentTenant->set($context);

        return $next($request);
    }
}
