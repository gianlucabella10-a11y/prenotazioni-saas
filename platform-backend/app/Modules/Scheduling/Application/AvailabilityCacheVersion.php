<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Versioned availability cache keys (docs/33 #34): instead of pattern
 * deletes, every (tenant, staff, local day) scope has a monotonically
 * increasing version; bumping it is O(1) invalidation — stale entries
 * simply expire by TTL.
 */
final readonly class AvailabilityCacheVersion
{
    private const VERSION_TTL_SECONDS = 86400;

    public function __construct(private Cache $cache)
    {
    }

    public function current(int $tenantId, int $staffMemberId, string $localDate): int
    {
        return (int) $this->cache->remember(
            $this->versionKey($tenantId, $staffMemberId, $localDate),
            self::VERSION_TTL_SECONDS,
            static fn (): int => 1,
        );
    }

    /** Invalidate one staff member's day after a write affecting it. */
    public function bump(int $tenantId, int $staffMemberId, string $localDate): void
    {
        $key = $this->versionKey($tenantId, $staffMemberId, $localDate);

        if (! $this->cache->add($key, 2, self::VERSION_TTL_SECONDS)) {
            $this->cache->increment($key);
        }
    }

    /** @param list<string> $localDates */
    public function bumpMany(int $tenantId, int $staffMemberId, array $localDates): void
    {
        foreach (array_unique($localDates) as $date) {
            $this->bump($tenantId, $staffMemberId, $date);
        }
    }

    public function slotsKey(int $tenantId, int $staffMemberId, string $localDate, int $serviceMinutes): string
    {
        $version = $this->current($tenantId, $staffMemberId, $localDate);

        return "t:{$tenantId}:avail:{$staffMemberId}:{$localDate}:{$serviceMinutes}:v{$version}";
    }

    private function versionKey(int $tenantId, int $staffMemberId, string $localDate): string
    {
        return "t:{$tenantId}:avail-ver:{$staffMemberId}:{$localDate}";
    }
}
