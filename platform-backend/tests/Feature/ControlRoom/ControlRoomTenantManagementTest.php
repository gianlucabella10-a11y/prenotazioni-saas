<?php

declare(strict_types=1);

namespace Tests\Feature\ControlRoom;

use App\Foundation\Enums\UserType;
use App\Models\User;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\TenantManagement\Infrastructure\Models\Plan;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Operatività della Control Room: creazione cliente (via ProvisionTenant),
 * lista con ricerca/filtro, transizioni di stato, invito. Il super-admin
 * gestisce un cliente dall'acquisizione alla configurazione senza DB.
 */
final class ControlRoomTenantManagementTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function actingSuperAdmin(): User
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function seedBasePlan(): void
    {
        $this->bypassTenancy(fn () => Plan::query()->updateOrCreate(
            ['code' => 'base'],
            Plan::factory()->raw(['code' => 'base']),
        ));
    }

    public function test_super_admin_creates_a_tenant_through_provisioning(): void
    {
        $this->actingSuperAdmin();
        $this->seedBasePlan();

        $this->post('/control-room/clienti', [
            'display_name' => 'Barberia Demo',
            'sector' => 'barber',
            'admin_email' => 'titolare@demo.it',
            'phone' => '0951234567',
            'plan_code' => 'base',
            'primary_color' => '#123456',
        ])->assertRedirect();

        $tenant = $this->bypassTenancy(
            fn (): Tenant => Tenant::query()->where('display_name', 'Barberia Demo')->firstOrFail()
        );

        self::assertSame('onboarding', $tenant->status->value);

        $owner = $this->bypassTenancy(fn (): ?User => User::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', UserType::TenantAdmin->value)
            ->first());

        self::assertNotNull($owner);
        self::assertSame('titolare@demo.it', $owner->email);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'titolare@demo.it']);

        $brand = $this->bypassTenancy(fn (): ?BrandProfile => BrandProfile::query()
            ->where('tenant_id', $tenant->id)->first());

        self::assertNotNull($brand);
        self::assertSame('#123456', $brand->primary_color);
    }

    public function test_health_sector_requires_explicit_module(): void
    {
        $this->actingSuperAdmin();
        $this->seedBasePlan();

        $this->post('/control-room/clienti', [
            'display_name' => 'Studio Dentistico',
            'sector' => 'dental',
            'admin_email' => 'dentista@demo.it',
            'plan_code' => 'base',
        ])->assertSessionHasErrors('display_name');

        $this->assertDatabaseMissing('tenants', ['display_name' => 'Studio Dentistico']);
    }

    public function test_list_filters_by_status_and_search(): void
    {
        $this->actingSuperAdmin();

        $this->bypassTenancy(function (): void {
            Tenant::factory()->create(['display_name' => 'Alpha Salon', 'status' => 'active']);
            Tenant::factory()->create(['display_name' => 'Beta Spa', 'status' => 'suspended']);
        });

        $this->get('/control-room/clienti?status=suspended')
            ->assertOk()->assertSee('Beta Spa')->assertDontSee('Alpha Salon');

        $this->get('/control-room/clienti?q=Alpha')
            ->assertOk()->assertSee('Alpha Salon')->assertDontSee('Beta Spa');
    }

    public function test_suspend_then_reactivate(): void
    {
        $this->actingSuperAdmin();
        $tenant = $this->bypassTenancy(fn (): Tenant => Tenant::factory()->create(['status' => 'active']));

        $this->post("/control-room/clienti/{$tenant->uuid}/sospendi")->assertRedirect();
        self::assertSame('suspended', $this->tenantStatus($tenant));

        $this->post("/control-room/clienti/{$tenant->uuid}/riattiva")->assertRedirect();
        self::assertSame('active', $this->tenantStatus($tenant));
    }

    public function test_terminate_archives_tenant_and_is_final(): void
    {
        $this->actingSuperAdmin();
        $tenant = $this->bypassTenancy(fn (): Tenant => Tenant::factory()->create(['status' => 'active']));

        $this->post("/control-room/clienti/{$tenant->uuid}/termina")->assertRedirect();
        self::assertSame('terminated', $this->tenantStatus($tenant));

        // Terminated è uno stato finale: nessuna transizione successiva è ammessa.
        $this->post("/control-room/clienti/{$tenant->uuid}/riattiva")->assertSessionHas('error');
        self::assertSame('terminated', $this->tenantStatus($tenant));
    }

    public function test_invalid_transition_is_reported_and_state_unchanged(): void
    {
        $this->actingSuperAdmin();
        $tenant = $this->bypassTenancy(fn (): Tenant => Tenant::factory()->create(['status' => 'onboarding']));

        // Sospendere da onboarding non è una transizione valida.
        $this->post("/control-room/clienti/{$tenant->uuid}/sospendi")->assertSessionHas('error');
        self::assertSame('onboarding', $this->tenantStatus($tenant));
    }

    public function test_tenant_detail_page_renders(): void
    {
        $this->actingSuperAdmin();
        $tenant = $this->bypassTenancy(fn (): Tenant => Tenant::factory()->create([
            'display_name' => 'Scheda Demo',
            'status' => 'active',
        ]));

        $this->get("/control-room/clienti/{$tenant->uuid}")
            ->assertOk()
            ->assertSee('Scheda Demo')
            ->assertSee('API key')
            ->assertSee($tenant->api_key);
    }

    public function test_invite_can_be_regenerated(): void
    {
        $this->actingSuperAdmin();

        $tenant = $this->bypassTenancy(fn (): Tenant => Tenant::factory()->create(['status' => 'active']));
        $this->bypassTenancy(fn (): User => User::factory()->create([
            'type' => UserType::TenantAdmin,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'email' => 'owner@demo.it',
        ]));

        $this->post("/control-room/clienti/{$tenant->uuid}/invito/rigenera")
            ->assertRedirect()
            ->assertSessionHas('invite_link');

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'owner@demo.it']);
    }

    private function tenantStatus(Tenant $tenant): string
    {
        return $this->bypassTenancy(fn (): string => Tenant::query()->find($tenant->id)->status->value);
    }
}
