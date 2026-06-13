<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain;

/**
 * Tenant lifecycle (docs/08 Flusso 9). Transitions are validated by
 * canTransitionTo so billing webhooks and admin actions cannot put a tenant
 * in an inconsistent state.
 */
enum TenantStatus: string
{
    case Onboarding = 'onboarding';
    case Active = 'active';
    case AtRisk = 'at_risk';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, match ($this) {
            self::Onboarding => [self::Active, self::Terminated],
            self::Active => [self::AtRisk, self::Suspended, self::Terminated],
            self::AtRisk => [self::Active, self::Suspended, self::Terminated],
            self::Suspended => [self::Active, self::Terminated],
            self::Terminated => [],
        }, true);
    }

    /** Customers can authenticate and book only against operating tenants. */
    public function acceptsCustomerTraffic(): bool
    {
        return $this === self::Active || $this === self::AtRisk;
    }

    /** The config endpoint serves every status (graceful degradation, docs/27 §7). */
    public function isTerminal(): bool
    {
        return $this === self::Terminated;
    }
}
