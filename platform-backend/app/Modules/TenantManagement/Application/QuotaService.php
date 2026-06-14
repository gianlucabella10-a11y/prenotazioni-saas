<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application;

use App\Foundation\Http\ApiException;
use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\TenantManagement\Infrastructure\Models\Subscription;

/**
 * Plan quota enforcement (docs/28 §4): ceilings live in plans.quotas and
 * are checked at creation time. Exceeding returns a commercial 422 with a
 * stable code the clients turn into an upsell prompt — never a silent block.
 */
final readonly class QuotaService
{
    public function __construct(private CurrentTenant $currentTenant) {}

    /**
     * @param  string  $quotaKey  e.g. max_staff, max_locations, max_services
     * @param  int  $currentCount  the tenant's current usage of the resource
     */
    public function assertWithinQuota(string $quotaKey, int $currentCount): void
    {
        $limit = $this->limitFor($quotaKey);

        if ($limit !== null && $currentCount >= $limit) {
            throw ApiException::unprocessable(
                'quota_exceeded',
                'The current plan limit for this resource has been reached.',
                ['quota' => $quotaKey, 'limit' => $limit],
            );
        }
    }

    private function limitFor(string $quotaKey): ?int
    {
        $subscription = Subscription::query()
            ->where('tenant_id', $this->currentTenant->id())
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->latest('id')
            ->with('plan')
            ->first();

        return $subscription?->plan?->quota($quotaKey);
    }
}
