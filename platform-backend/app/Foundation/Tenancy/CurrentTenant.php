<?php

declare(strict_types=1);

namespace App\Foundation\Tenancy;

use App\Foundation\Tenancy\Exceptions\TenantContextMissing;

/**
 * Per-request holder of the resolved TenantContext.
 *
 * Registered as a scoped singleton (reset between requests and jobs in
 * Octane-like environments). Tenant-bound queries fail closed when no
 * context is bound: cross-tenant access is only possible through the
 * explicit, auditable bypass API used by platform-level code.
 */
final class CurrentTenant
{
    private ?TenantContext $context = null;

    private bool $bypassed = false;

    public function set(TenantContext $context): void
    {
        $this->context = $context;
    }

    public function clear(): void
    {
        $this->context = null;
    }

    public function bound(): bool
    {
        return $this->context !== null;
    }

    public function get(): TenantContext
    {
        if ($this->context === null) {
            throw TenantContextMissing::make();
        }

        return $this->context;
    }

    public function id(): int
    {
        return $this->get()->id;
    }

    /**
     * Execute platform-level code outside any tenant scope.
     *
     * The only sanctioned way to run cross-tenant queries (provisioning,
     * retention jobs, super admin listings). Restores the previous state
     * even on failure.
     *
     * @template TReturn
     *
     * @param callable(): TReturn $callback
     *
     * @return TReturn
     */
    public function bypass(callable $callback): mixed
    {
        $previous = $this->bypassed;
        $this->bypassed = true;

        try {
            return $callback();
        } finally {
            $this->bypassed = $previous;
        }
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }
}
