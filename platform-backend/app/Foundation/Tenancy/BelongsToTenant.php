<?php

declare(strict_types=1);

namespace App\Foundation\Tenancy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;

/**
 * Marks an Eloquent model as tenant-bound.
 *
 * Adds the global TenantScope and auto-fills tenant_id on create so that
 * application code can never forget (or spoof) the column. Models without
 * this trait must be explicitly platform-level (enforced by the
 * architecture test in tests/Architecture).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model): void {
            $current = app(CurrentTenant::class);

            if ($model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', $current->id());

                return;
            }

            // A pre-set tenant_id is legitimate only for platform code
            // running under the explicit bypass (e.g. provisioning).
            if (! $current->isBypassed() && (int) $model->getAttribute('tenant_id') !== $current->id()) {
                throw Exceptions\TenantContextMissing::make();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
