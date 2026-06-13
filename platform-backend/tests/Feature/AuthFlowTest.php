<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RefreshToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class AuthFlowTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    public function test_customer_can_register_and_receives_tokens(): void
    {
        $env = $this->provisionBookableTenant();

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'nuovo@example.com',
            'password' => 'password-sicura-123',
            'first_name' => 'Luca',
            'privacy_accepted' => true,
        ], $this->tenantKeyHeaders($env['tenant']));

        $response->assertCreated()
            ->assertJsonStructure(['access_token', 'refresh_token', 'user' => ['uuid', 'type']])
            ->assertJsonPath('user.type', 'customer')
            ->assertJsonPath('user.email_verified', false);

        // S1: NO CRM record exists until the email is verified
        // (see EmailVerificationTest for the full ownership flow).
        $this->bindTenant($env['tenant']);
        $this->assertDatabaseMissing('customers', ['email' => 'nuovo@example.com']);
    }

    public function test_registration_does_not_link_existing_crm_customer_before_verification(): void
    {
        $env = $this->provisionBookableTenant();

        $existing = $this->bypassTenancy(fn () => \App\Modules\Customers\Infrastructure\Models\Customer::factory()->create([
            'tenant_id' => $env['tenant']->id,
            'email' => 'storico@example.com',
            'user_id' => null,
            'source' => 'staff',
        ]));

        $this->postJson('/api/v1/auth/register', [
            'email' => 'storico@example.com',
            'password' => 'password-sicura-123',
            'first_name' => 'Maria',
            'privacy_accepted' => true,
        ], $this->tenantKeyHeaders($env['tenant']))->assertCreated();

        // The link is deferred to email verification (S1 fix).
        self::assertNull($existing->fresh()->user_id);
    }

    public function test_login_with_wrong_password_fails_with_stable_code(): void
    {
        $env = $this->provisionBookableTenant();
        $this->createCustomerUser($env['tenant']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'cliente@example.com',
            'password' => 'sbagliata-del-tutto',
        ], $this->tenantKeyHeaders($env['tenant']))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_refresh_rotates_and_reuse_revokes_family(): void
    {
        $env = $this->provisionBookableTenant();
        $actors = $this->createCustomerUser($env['tenant']);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'cliente@example.com',
            'password' => 'secret-password-123',
        ], $this->tenantKeyHeaders($env['tenant']))->assertOk();

        $firstRefresh = $login->json('refresh_token');

        // Rotation succeeds and returns a NEW token.
        $rotated = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $firstRefresh])
            ->assertOk()
            ->json('refresh_token');

        self::assertNotSame($firstRefresh, $rotated);

        // Reusing the rotated-away token is theft: whole family revoked.
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $firstRefresh])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'refresh_token_reused');

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $rotated])
            ->assertStatus(401);

        self::assertSame(
            0,
            RefreshToken::query()->where('user_id', $actors['user']->id)->whereNull('revoked_at')->count(),
        );
    }

    public function test_login_is_rejected_for_suspended_tenant(): void
    {
        $env = $this->provisionBookableTenant();
        $this->createCustomerUser($env['tenant']);

        $this->bypassTenancy(function () use ($env): void {
            $env['tenant']->forceFill(['status' => \App\Modules\TenantManagement\Domain\TenantStatus::Suspended])->save();
        });
        app(\App\Foundation\Tenancy\TenantRegistry::class)->forget($env['tenant']->id);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'cliente@example.com',
            'password' => 'secret-password-123',
        ], $this->tenantKeyHeaders($env['tenant']))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'tenant_not_operating');
    }
}
