<?php

declare(strict_types=1);

namespace Tests\Feature\ControlRoom;

use App\Foundation\Enums\UserType;
use App\Models\User;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Isolamento della Control Room: solo i super_admin entrano. Un titolare,
 * uno staff o un cliente — anche se autenticati sul dashboard (guard `web`)
 * — NON possono vedere né raggiungere la console proprietaria.
 */
final class ControlRoomAccessTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
    }

    private function tenantAdmin(): User
    {
        $tenant = $this->bypassTenancy(fn (): Tenant => Tenant::factory()->create());

        return $this->bypassTenancy(fn (): User => User::factory()->create([
            'type' => UserType::TenantAdmin,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'password' => 'TenantPass2026!',
        ]));
    }

    public function test_guest_is_redirected_to_control_room_login(): void
    {
        $this->get('/control-room')->assertRedirect(route('control.login'));
    }

    public function test_super_admin_can_open_the_control_room(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')->get('/control-room')->assertOk();
    }

    public function test_tenant_admin_is_forbidden_even_on_the_admin_guard(): void
    {
        $this->actingAs($this->tenantAdmin(), 'admin')->get('/control-room')->assertForbidden();
    }

    public function test_customer_is_forbidden(): void
    {
        $customer = $this->bypassTenancy(fn (): User => User::factory()->create(['type' => UserType::Customer]));

        $this->actingAs($customer, 'admin')->get('/control-room')->assertForbidden();
    }

    public function test_dashboard_web_session_does_not_grant_control_room(): void
    {
        // Privilege escalation impossibile: una sessione del dashboard cliente
        // (guard `web`) non concede l'accesso alla guard `admin`.
        $this->actingAs($this->tenantAdmin(), 'web')->get('/control-room')
            ->assertRedirect(route('control.login'));
    }

    public function test_login_rejects_non_super_admin_credentials(): void
    {
        $owner = $this->tenantAdmin();

        $this->post('/control-room/login', [
            'email' => $owner->email,
            'password' => 'TenantPass2026!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_protected_write_routes_reject_non_super_admin(): void
    {
        $tenant = $this->bypassTenancy(fn (): Tenant => Tenant::factory()->create(['status' => 'active']));

        $this->actingAs($this->tenantAdmin(), 'admin')
            ->post("/control-room/clienti/{$tenant->uuid}/sospendi")
            ->assertForbidden();
    }
}
