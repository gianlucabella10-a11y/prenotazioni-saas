<?php

declare(strict_types=1);

namespace App\Foundation\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope applied by BelongsToTenant: every query on a tenant-bound
 * model is constrained to the current tenant (defence level 2, docs/28).
 *
 * Fails closed: with no bound context and no explicit bypass, the query
 * throws instead of running unscoped.
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $current = app(CurrentTenant::class);

        if ($current->isBypassed()) {
            return;
        }

        $builder->where(
            $model->qualifyColumn('tenant_id'),
            $current->id() // throws TenantContextMissing when unbound
        );
    }
}
