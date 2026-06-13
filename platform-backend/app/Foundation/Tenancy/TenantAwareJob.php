<?php

declare(strict_types=1);

namespace App\Foundation\Tenancy;

/**
 * Queue jobs touching tenant data serialize the tenant id and rebind the
 * TenantContext before doing any work (docs/28 §2): workers run without a
 * request, so the context must be rehydrated explicitly.
 */
trait TenantAwareJob
{
    public int $tenantId;

    protected function bindTenantContext(): void
    {
        $registry = app(TenantRegistry::class);
        $context = $registry->findById($this->tenantId);

        if ($context === null) {
            throw Exceptions\TenantContextMissing::make();
        }

        app(CurrentTenant::class)->set($context);
    }
}
