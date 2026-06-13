<?php

declare(strict_types=1);

namespace App\Foundation\Tenancy;

use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Cached resolution of TenantContext (the "tenant registry" of docs/28 §3).
 *
 * Cache-first with DB fallback; invalidated explicitly on tenant state or
 * configuration changes via forget(). A Redis outage therefore degrades to
 * slower DB reads, never to errors or stale suspensions.
 */
final readonly class TenantRegistry
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(private Cache $cache)
    {
    }

    public function findByApiKey(string $apiKey): ?TenantContext
    {
        // The cache key hashes the attacker-controlled header value: random
        // probing cannot grow unbounded plain-text keys in Redis, and the
        // negative result is cached too (cheap repeated misses).
        $tenantId = $this->cache->remember(
            'tenant:key:' . hash('sha256', $apiKey),
            self::CACHE_TTL_SECONDS,
            fn (): int => (int) Tenant::query()->where('api_key', $apiKey)->value('id'),
        );

        return $tenantId > 0 ? $this->findById($tenantId) : null;
    }

    public function findById(int $tenantId): ?TenantContext
    {
        /** @var array<string, mixed>|null $snapshot */
        $snapshot = $this->cache->remember(
            self::metaKey($tenantId),
            self::CACHE_TTL_SECONDS,
            function () use ($tenantId): ?array {
                $tenant = Tenant::query()->with('activeSubscription.plan', 'featureOverrides')->find($tenantId);

                return $tenant?->toContextSnapshot();
            },
        );

        return $snapshot === null ? null : TenantContextFactory::fromSnapshot($snapshot);
    }

    /** Invalidate after any change affecting status, plan, features or settings. */
    public function forget(int $tenantId): void
    {
        $this->cache->forget(self::metaKey($tenantId));
    }

    private static function metaKey(int $tenantId): string
    {
        return "tenant:meta:{$tenantId}";
    }
}
