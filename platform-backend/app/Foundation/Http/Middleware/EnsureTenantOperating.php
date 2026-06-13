<?php

declare(strict_types=1);

namespace App\Foundation\Http\Middleware;

use App\Foundation\Http\ApiException;
use App\Foundation\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks customer-facing operations for suspended/terminated tenants
 * (docs/08 Flusso 9). The /app/config endpoint deliberately skips this
 * middleware so the app can render the courtesy screen (docs/27 §7).
 */
final class EnsureTenantOperating
{
    public function __construct(private readonly CurrentTenant $currentTenant)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->currentTenant->get()->status->acceptsCustomerTraffic()) {
            throw ApiException::forbidden(
                'tenant_not_operating',
                'This service is temporarily unavailable.'
            );
        }

        return $next($request);
    }
}
