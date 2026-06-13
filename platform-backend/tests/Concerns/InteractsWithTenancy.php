<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Foundation\Auth\JwtService;
use App\Foundation\Tenancy\CurrentTenant;
use App\Foundation\Tenancy\TenantRegistry;
use App\Models\User;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\Catalog\Infrastructure\Models\Location;
use App\Modules\Catalog\Infrastructure\Models\Service;
use App\Modules\Catalog\Infrastructure\Models\ServiceVariant;
use App\Modules\Customers\Infrastructure\Models\Customer;
use App\Modules\Staff\Infrastructure\Models\StaffMember;
use App\Modules\TenantManagement\Infrastructure\Models\Plan;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;

/**
 * Test scaffolding for multi-tenant scenarios: builds a fully bookable
 * tenant (plan, brand, location with 7-day opening, one service+variant,
 * one staff member on schedule) and helpers to act as its users.
 */
trait InteractsWithTenancy
{
    /**
     * @return array{
     *     tenant: Tenant, plan: Plan, location: Location, service: Service,
     *     variant: ServiceVariant, staff: StaffMember
     * }
     */
    protected function provisionBookableTenant(array $tenantOverrides = [], array $variantOverrides = []): array
    {
        return $this->bypassTenancy(function () use ($tenantOverrides, $variantOverrides): array {
            $plan = Plan::factory()->pro()->create();
            $tenant = Tenant::factory()->create($tenantOverrides);

            $tenant->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_end' => now()->addMonth(),
            ]);

            BrandProfile::factory()->create(['tenant_id' => $tenant->id]);

            $location = Location::factory()->create(['tenant_id' => $tenant->id]);

            foreach (range(0, 6) as $weekday) {
                $location->schedules()->create([
                    'tenant_id' => $tenant->id,
                    'weekday' => $weekday,
                    'start_time' => '09:00',
                    'end_time' => '19:00',
                ]);
            }

            $service = Service::factory()->create(['tenant_id' => $tenant->id]);
            $variant = ServiceVariant::factory()->create([
                'tenant_id' => $tenant->id,
                'service_id' => $service->id,
                ...$variantOverrides,
            ]);

            $location->services()->attach($service->id, ['tenant_id' => $tenant->id]);

            $staff = StaffMember::factory()->create(['tenant_id' => $tenant->id]);
            $staff->services()->attach($service->id, ['tenant_id' => $tenant->id]);

            foreach (range(0, 6) as $weekday) {
                $staff->schedules()->create([
                    'tenant_id' => $tenant->id,
                    'location_id' => $location->id,
                    'weekday' => $weekday,
                    'start_time' => '09:00',
                    'end_time' => '19:00',
                ]);
            }

            return compact('tenant', 'plan', 'location', 'service', 'variant', 'staff');
        });
    }

    protected function bindTenant(Tenant $tenant): void
    {
        $context = app(TenantRegistry::class)->findById($tenant->id);

        assert($context !== null);

        app(CurrentTenant::class)->set($context);
    }

    /**
     * @template TReturn
     *
     * @param callable(): TReturn $callback
     *
     * @return TReturn
     */
    protected function bypassTenancy(callable $callback): mixed
    {
        return app(CurrentTenant::class)->bypass($callback);
    }

    protected function createCustomerUser(Tenant $tenant, string $email = 'cliente@example.com'): array
    {
        return $this->bypassTenancy(function () use ($tenant, $email): array {
            $user = User::factory()->customer()->create([
                'tenant_id' => $tenant->id,
                'email' => $email,
            ]);

            $customer = Customer::factory()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'email' => $email,
            ]);

            return ['user' => $user, 'customer' => $customer];
        });
    }

    protected function createTenantAdmin(Tenant $tenant, bool $mfaEnforced = false): User
    {
        return $this->bypassTenancy(fn (): User => User::factory()
            ->tenantAdmin($mfaEnforced)
            ->create(['tenant_id' => $tenant->id]));
    }

    protected function accessTokenFor(User $user): string
    {
        return app(JwtService::class)->issueAccessToken($user);
    }

    /** @return array<string, string> */
    protected function authHeaders(User $user, Tenant $tenant): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessTokenFor($user),
            'X-Tenant-Key' => $tenant->api_key,
            'Accept' => 'application/json',
        ];
    }

    /** @return array<string, string> */
    protected function tenantKeyHeaders(Tenant $tenant): array
    {
        return [
            'X-Tenant-Key' => $tenant->api_key,
            'Accept' => 'application/json',
        ];
    }
}
