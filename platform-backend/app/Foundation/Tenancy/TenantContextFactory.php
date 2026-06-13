<?php

declare(strict_types=1);

namespace App\Foundation\Tenancy;

use App\Modules\TenantManagement\Domain\TenantStatus;

/**
 * Rebuilds a TenantContext from the cacheable snapshot produced by
 * Tenant::toContextSnapshot(). Kept separate from the model so the cache
 * payload stays a plain array (safe to serialize, no Eloquent in Redis).
 */
final class TenantContextFactory
{
    /** @param array<string, mixed> $snapshot */
    public static function fromSnapshot(array $snapshot): TenantContext
    {
        return new TenantContext(
            id: (int) $snapshot['id'],
            uuid: (string) $snapshot['uuid'],
            status: TenantStatus::from((string) $snapshot['status']),
            timezone: (string) $snapshot['timezone'],
            locale: (string) $snapshot['locale'],
            healthDataEnabled: (bool) $snapshot['health_data_enabled'],
            features: (array) $snapshot['features'],
            settings: (array) $snapshot['settings'],
        );
    }
}
