<?php

declare(strict_types=1);

namespace App\Foundation\Tenancy\Exceptions;

use RuntimeException;

/**
 * Raised when tenant-bound data is touched without a bound TenantContext.
 *
 * This is always a programming error: either the request skipped tenant
 * resolution middleware, or platform code forgot the explicit bypass API.
 * Failing closed here is defence level 3 of the isolation design (docs/28).
 */
final class TenantContextMissing extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'No tenant context is bound. Tenant-bound models require a resolved tenant; '
            . 'platform-level code must use CurrentTenant::bypass().'
        );
    }
}
