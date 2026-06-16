<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Push device registry (Fase 4): registration, idempotency and — critically
 * for a multi-tenant SaaS — isolation. A user can only touch their own
 * devices, and a device always carries the owner's tenant.
 */
final class DeviceRegistrationTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function verifiedCustomer(Tenant $tenant, string $email): User
    {
        $actors = $this->createCustomerUser($tenant, $email);

        $this->bypassTenancy(
            fn () => $actors['user']->forceFill(['email_verified_at' => now()])->save()
        );

        return $actors['user'];
    }

    public function test_customer_registers_a_device_with_metadata(): void
    {
        $env = $this->provisionBookableTenant();
        $user = $this->verifiedCustomer($env['tenant'], 'cliente@example.com');

        $this->putJson('/api/v1/me/devices', [
            'platform' => 'android',
            'fcm_token' => 'token-abc',
            'device_name' => 'Pixel 8',
            'app_version' => '1.0.0',
        ], $this->authHeaders($user, $env['tenant']))
            ->assertCreated()
            ->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseHas('devices', [
            'user_id' => $user->id,
            'tenant_id' => $env['tenant']->id,
            'fcm_token' => 'token-abc',
            'device_name' => 'Pixel 8',
            'app_version' => '1.0.0',
        ]);
    }

    public function test_registration_is_idempotent_per_token(): void
    {
        $env = $this->provisionBookableTenant();
        $user = $this->verifiedCustomer($env['tenant'], 'cliente@example.com');
        $headers = $this->authHeaders($user, $env['tenant']);

        $this->putJson('/api/v1/me/devices', ['platform' => 'ios', 'fcm_token' => 'tok'], $headers)->assertCreated();
        $this->putJson('/api/v1/me/devices', ['platform' => 'ios', 'fcm_token' => 'tok', 'app_version' => '1.1.0'], $headers)->assertCreated();

        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseHas('devices', ['fcm_token' => 'tok', 'app_version' => '1.1.0']);
    }

    public function test_unregister_only_affects_own_device(): void
    {
        $envA = $this->provisionBookableTenant();
        $userA = $this->verifiedCustomer($envA['tenant'], 'a@example.com');

        $envB = $this->provisionBookableTenant();
        $userB = $this->verifiedCustomer($envB['tenant'], 'b@example.com');

        $this->putJson('/api/v1/me/devices', ['platform' => 'android', 'fcm_token' => 'token-of-A'],
            $this->authHeaders($userA, $envA['tenant']))->assertCreated();

        // User B tries to delete A's token: nothing happens (scoped to B).
        $this->deleteJson('/api/v1/me/devices', ['fcm_token' => 'token-of-A'],
            $this->authHeaders($userB, $envB['tenant']))->assertOk();

        $this->assertDatabaseHas('devices', ['user_id' => $userA->id, 'fcm_token' => 'token-of-A']);

        // The owner can remove it.
        $this->deleteJson('/api/v1/me/devices', ['fcm_token' => 'token-of-A'],
            $this->authHeaders($userA, $envA['tenant']))->assertOk();

        $this->assertDatabaseMissing('devices', ['fcm_token' => 'token-of-A']);
    }

    public function test_same_token_string_is_isolated_per_user(): void
    {
        $envA = $this->provisionBookableTenant();
        $userA = $this->verifiedCustomer($envA['tenant'], 'a@example.com');

        $envB = $this->provisionBookableTenant();
        $userB = $this->verifiedCustomer($envB['tenant'], 'b@example.com');

        $this->putJson('/api/v1/me/devices', ['platform' => 'ios', 'fcm_token' => 'shared'],
            $this->authHeaders($userA, $envA['tenant']))->assertCreated();
        $this->putJson('/api/v1/me/devices', ['platform' => 'ios', 'fcm_token' => 'shared'],
            $this->authHeaders($userB, $envB['tenant']))->assertCreated();

        // Two distinct rows, each bound to its own user+tenant.
        $this->assertDatabaseCount('devices', 2);
        $this->assertDatabaseHas('devices', ['user_id' => $userA->id, 'tenant_id' => $envA['tenant']->id, 'fcm_token' => 'shared']);
        $this->assertDatabaseHas('devices', ['user_id' => $userB->id, 'tenant_id' => $envB['tenant']->id, 'fcm_token' => 'shared']);
    }

    public function test_guest_cannot_register_a_device(): void
    {
        $env = $this->provisionBookableTenant();

        $this->putJson('/api/v1/me/devices', ['platform' => 'android', 'fcm_token' => 'x'],
            $this->tenantKeyHeaders($env['tenant']))
            ->assertStatus(401);
    }
}
