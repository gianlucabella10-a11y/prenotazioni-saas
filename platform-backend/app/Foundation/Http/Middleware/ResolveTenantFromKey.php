<?php

declare(strict_types=1);

namespace App\Foundation\Http\Middleware;

use App\Foundation\Http\ApiException;
use App\Foundation\Tenancy\CurrentTenant;
use App\Foundation\Tenancy\TenantRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant for public, pre-authentication endpoints of the white
 * label client app from the X-Tenant-Key header — the public key compiled
 * into each tenant's build (docs/25 §1).
 */
final class ResolveTenantFromKey
{
    public const HEADER = 'X-Tenant-Key';

    public function __construct(
        private readonly TenantRegistry $registry,
        private readonly CurrentTenant $currentTenant,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header(self::HEADER, '');

        if ($key === '') {
            throw ApiException::unauthorized('missing_tenant_key', 'The X-Tenant-Key header is required.');
        }

        $context = $this->registry->findByApiKey($key);

        if ($context === null) {
            throw ApiException::unauthorized('invalid_tenant_key', 'The tenant key is not recognized.');
        }

        $this->currentTenant->set($context);

        return $next($request);
    }
}
