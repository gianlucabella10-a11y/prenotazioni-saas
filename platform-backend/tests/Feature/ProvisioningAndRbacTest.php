<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\TenantManagement\Infrastructure\Models\Plan;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class ProvisioningAndRbacTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
    }

    /** @return array<string, string> */
    private function adminHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessTokenFor($this->superAdmin()),
            'Accept' => 'application/json',
        ];
    }

    private function provisionPayload(): array
    {
        Plan::query()->updateOrCreate(['code' => 'base'], Plan::factory()->raw(['code' => 'base']));

        return [
            'legal_name' => 'Barberia Rossi S.r.l.',
            'display_name' => 'Barberia Rossi',
            'sector' => 'barber',
            'timezone' => 'Europe/Rome',
            'locale' => 'it',
            'plan_code' => 'base',
            'app_name' => 'Barberia Rossi',
            'admin_email' => 'titolare@barberiarossi.it',
        ];
    }

    public function test_super_admin_provisions_a_ready_to_configure_tenant(): void
    {
        $response = $this->postJson('/api/v1/admin/tenants', $this->provisionPayload(), $this->adminHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'onboarding')
            ->assertJsonStructure(['invite_token', 'tenant_api_key', 'admin' => ['email']]);

        $tenant = $this->bypassTenancy(
            fn () => Tenant::query()->where('uuid', $response->json('data.uuid'))->firstOrFail()
        );

        // The starter pack exists: brand, location with hours, catalog, admin.
        $this->bindTenant($tenant);
        self::assertSame(1, \App\Modules\Branding\Infrastructure\Models\BrandProfile::query()->count());
        self::assertSame(1, \App\Modules\Catalog\Infrastructure\Models\Location::query()->count());
        self::assertGreaterThan(0, \App\Modules\Catalog\Infrastructure\Models\Service::query()->count());
        self::assertGreaterThan(0, \App\Modules\Scheduling\Infrastructure\Models\LocationSchedule::query()->count());

        $admin = $this->bypassTenancy(
            fn () => User::query()->where('tenant_id', $tenant->id)->where('type', 'tenant_admin')->firstOrFail()
        );

        self::assertTrue($admin->mfa_enforced, 'Tenant admin MFA must be enforced (docs/14 §2).');

        // The white label config is immediately servable.
        $this->getJson('/api/v1/app/config', ['X-Tenant-Key' => $response->json('tenant_api_key')])
            ->assertOk()
            ->assertJsonPath('app_name', 'Barberia Rossi');
    }

    public function test_healthcare_sector_requires_explicit_health_module(): void
    {
        $payload = array_merge($this->provisionPayload(), ['sector' => 'dental']);

        $this->postJson('/api/v1/admin/tenants', $payload, $this->adminHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'health_module_required');
    }

    public function test_tenant_lifecycle_transitions_are_validated(): void
    {
        $created = $this->postJson('/api/v1/admin/tenants', $this->provisionPayload(), $this->adminHeaders());
        $uuid = $created->json('data.uuid');
        $headers = $this->adminHeaders();

        // onboarding -> active -> suspended -> active is legal…
        $this->postJson("/api/v1/admin/tenants/{$uuid}/activate", [], $headers)->assertOk();
        $this->postJson("/api/v1/admin/tenants/{$uuid}/suspend", [], $headers)
            ->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->postJson("/api/v1/admin/tenants/{$uuid}/reactivate", [], $headers)->assertOk();

        // …re-suspending an active tenant twice in a row is fine, but
        // suspending from onboarding is not part of the machine.
        $fresh = $this->postJson('/api/v1/admin/tenants', array_merge(
            $this->provisionPayload(),
            ['admin_email' => 'altro@example.com'],
        ), $headers);

        $this->postJson('/api/v1/admin/tenants/' . $fresh->json('data.uuid') . '/suspend', [], $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_tenant_transition');
    }

    public function test_rbac_boundaries_between_surfaces(): void
    {
        $env = $this->provisionBookableTenant();
        $actors = $this->createCustomerUser($env['tenant']);
        $admin = $this->createTenantAdmin($env['tenant']);
        $staffUser = $this->bypassTenancy(fn (): User => User::factory()->staff()->create(['tenant_id' => $env['tenant']->id]));

        // Customer cannot reach management or platform surfaces.
        $this->getJson('/api/v1/manage/agenda?date=2026-06-15', $this->authHeaders($actors['user'], $env['tenant']))
            ->assertStatus(403);
        $this->getJson('/api/v1/admin/tenants', $this->authHeaders($actors['user'], $env['tenant']))
            ->assertStatus(403);

        // Staff reaches the agenda but not tenant configuration.
        $this->bypassTenancy(fn () => $env['staff']->update(['user_id' => $staffUser->id]));

        $this->getJson('/api/v1/manage/agenda?date=2026-06-15', $this->authHeaders($staffUser, $env['tenant']))
            ->assertOk();
        $this->getJson('/api/v1/manage/services', $this->authHeaders($staffUser, $env['tenant']))
            ->assertStatus(403);

        // Tenant admin cannot reach the platform surface.
        $this->getJson('/api/v1/admin/tenants', $this->authHeaders($admin, $env['tenant']))
            ->assertStatus(403);
    }

    public function test_staff_quota_is_enforced(): void
    {
        $env = $this->provisionBookableTenant();
        $admin = $this->createTenantAdmin($env['tenant']);
        $headers = $this->authHeaders($admin, $env['tenant']);

        $quota = $env['plan']->quota('max_staff'); // pro: 15

        $this->bypassTenancy(function () use ($env, $quota): void {
            \App\Modules\Staff\Infrastructure\Models\StaffMember::factory()
                ->count($quota - 1) // one already exists from provisioning
                ->create(['tenant_id' => $env['tenant']->id]);
        });

        $this->postJson('/api/v1/manage/staff', ['display_name' => 'Uno Di Troppo'], $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'quota_exceeded');
    }
}
