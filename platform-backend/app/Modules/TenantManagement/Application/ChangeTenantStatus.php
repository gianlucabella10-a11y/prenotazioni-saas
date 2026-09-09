<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Http\ApiException;
use App\Foundation\Tenancy\CurrentTenant;
use App\Foundation\Tenancy\TenantRegistry;
use App\Modules\TenantManagement\Domain\TenantStatus;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;

/**
 * Transizione di stato di un tenant, validata dalla macchina a stati
 * (TenantStatus::canTransitionTo). Punto unico di verità condiviso
 * dall'API super-admin (AdminTenantController) e dalla Control Room:
 * invalida la cache del registry e scrive l'audit.
 */
final readonly class ChangeTenantStatus
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private TenantRegistry $registry,
        private AuditLogger $audit,
    ) {}

    public function execute(string $uuid, TenantStatus $target, string $auditAction, int $actorUserId): Tenant
    {
        $tenant = $this->currentTenant->bypass(
            fn (): ?Tenant => Tenant::query()->where('uuid', $uuid)->first()
        );

        if ($tenant === null) {
            throw ApiException::notFound('tenant');
        }

        if (! $tenant->status->canTransitionTo($target)) {
            throw ApiException::unprocessable(
                'invalid_tenant_transition',
                "A tenant in status '{$tenant->status->value}' cannot move to '{$target->value}'.",
            );
        }

        $tenant->forceFill([
            'status' => $target,
            'suspended_at' => $target === TenantStatus::Suspended ? now() : null,
        ])->save();

        $this->registry->forget($tenant->id);

        $this->audit->log($auditAction, $actorUserId, [], $tenant->id, Tenant::class, $tenant->id);

        return $tenant;
    }
}
