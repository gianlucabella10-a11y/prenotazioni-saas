<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Models\RefreshToken;
use App\Modules\Customers\Infrastructure\Models\Customer;
use App\Modules\Scheduling\Infrastructure\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Fase 2+4 — account management & push foundation: profile, consents,
 * devices, Apple-compliant deletion (5.1.1(v)).
 */
final class AccountManagementTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    public function test_profile_read_and_update_sync_the_crm_record(): void
    {
        $env = $this->provisionBookableTenant();
        $actors = $this->createCustomerUser($env['tenant']);
        $headers = $this->authHeaders($actors['user'], $env['tenant']);

        $this->getJson('/api/v1/me', $headers)
            ->assertOk()
            ->assertJsonPath('data.email', 'cliente@example.com')
            ->assertJsonPath('data.email_verified', true);

        $this->patchJson('/api/v1/me', [
            'first_name' => 'Rinominato',
            'phone' => '+39 333 0000000',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Rinominato');

        self::assertSame(
            'Rinominato',
            $this->bypassTenancy(fn () => $actors['customer']->fresh()->first_name),
        );
    }

    public function test_marketing_consents_are_appended_with_timestamp(): void
    {
        $env = $this->provisionBookableTenant();
        $actors = $this->createCustomerUser($env['tenant']);
        $headers = $this->authHeaders($actors['user'], $env['tenant']);

        $this->putJson('/api/v1/me/consents', ['marketing_push' => true], $headers)->assertOk();
        $this->putJson('/api/v1/me/consents', ['marketing_push' => false], $headers)->assertOk();

        $rows = DB::table('consents')->where('kind', 'marketing_push')->orderBy('id')->get();

        // Append-only history: both decisions exist, latest wins.
        self::assertCount(2, $rows);
        self::assertTrue((bool) $rows[0]->granted);
        self::assertFalse((bool) $rows[1]->granted);
        self::assertNotNull($rows[1]->occurred_at);

        self::assertFalse(
            (bool) $this->bypassTenancy(fn () => $actors['customer']->fresh()->marketing_opt_in),
        );
    }

    public function test_device_registration_upserts_and_deletes(): void
    {
        $env = $this->provisionBookableTenant();
        $actors = $this->createCustomerUser($env['tenant']);
        $headers = $this->authHeaders($actors['user'], $env['tenant']);

        $this->putJson('/api/v1/me/devices', [
            'platform' => 'ios',
            'fcm_token' => 'token-abc',
        ], $headers)->assertCreated();

        // Same token again: refresh, not duplicate.
        $this->putJson('/api/v1/me/devices', [
            'platform' => 'ios',
            'fcm_token' => 'token-abc',
            'locale' => 'en',
        ], $headers)->assertCreated();

        self::assertSame(1, Device::query()->where('user_id', $actors['user']->id)->count());
        self::assertSame('en', Device::query()->where('user_id', $actors['user']->id)->value('locale'));

        $this->deleteJson('/api/v1/me/devices', ['fcm_token' => 'token-abc'], $headers)
            ->assertOk();

        self::assertSame(0, Device::query()->where('user_id', $actors['user']->id)->count());
    }

    public function test_account_deletion_is_immediate_and_apple_compliant(): void
    {
        $env = $this->provisionBookableTenant();
        $actors = $this->createCustomerUser($env['tenant']);

        // Give the account everything that must be cleaned up.
        $this->bypassTenancy(function () use ($env, $actors): void {
            Device::query()->create([
                'user_id' => $actors['user']->id,
                'platform' => 'ios',
                'fcm_token' => 't-1',
            ]);

            $actors['customer']->notes()->create([
                'tenant_id' => $env['tenant']->id,
                'visibility' => 'internal',
                'body' => 'preferenze personali',
            ]);

            Appointment::factory()->create([
                'tenant_id' => $env['tenant']->id,
                'customer_id' => $actors['customer']->id,
                'location_id' => $env['location']->id,
            ]);
        });

        // A live session exists.
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'cliente@example.com',
            'password' => 'secret-password-123',
        ], $this->tenantKeyHeaders($env['tenant']))->assertOk();

        $accessToken = $login->json('access_token');
        $refreshToken = $login->json('refresh_token');

        $this->deleteJson('/api/v1/me', [], ['Authorization' => "Bearer {$accessToken}"])
            ->assertOk()
            ->assertJsonPath('status', 'deleted');

        // 1. Every session is dead: the access token is rejected on the very
        //    next call (not at expiry) and the refresh token is revoked.
        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$accessToken}"])
            ->assertStatus(401);
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refreshToken])
            ->assertStatus(401);

        // 2. Login no longer possible.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'cliente@example.com',
            'password' => 'secret-password-123',
        ], $this->tenantKeyHeaders($env['tenant']))->assertStatus(401);

        // 3. User pseudonymized, devices and notes gone.
        $user = $actors['user']->fresh();
        self::assertSame('deleted', $user->status);
        self::assertNull($user->email);
        self::assertNull($user->first_name);
        self::assertSame(0, Device::query()->where('user_id', $user->id)->count());

        $customer = $this->bypassTenancy(fn () => $actors['customer']->fresh());
        self::assertNull($customer->user_id);
        self::assertNull($customer->email);
        self::assertSame('Account eliminato', $customer->first_name);
        self::assertSame(0, $this->bypassTenancy(fn () => $customer->notes()->count()));

        // 4. Tenant business records survive, pseudonymized.
        $this->bindTenant($env['tenant']);
        self::assertSame(1, Appointment::query()->where('customer_id', $customer->id)->count());

        // 5. Audit trail exists.
        self::assertSame(1, DB::table('audit_logs')->where('action', 'account.deleted')->count());
    }

    public function test_legal_urls_flow_from_brand_to_white_label_config(): void
    {
        $env = $this->provisionBookableTenant();
        $admin = $this->createTenantAdmin($env['tenant']);

        $this->putJson('/api/v1/manage/brand', [
            'privacy_policy_url' => 'https://salone.example/privacy',
            'terms_url' => 'https://salone.example/termini',
            'support_url' => 'https://salone.example/supporto',
        ], $this->authHeaders($admin, $env['tenant']))->assertOk();

        $this->getJson('/api/v1/app/config', $this->tenantKeyHeaders($env['tenant']))
            ->assertOk()
            ->assertJsonPath('legal.privacy_policy_url', 'https://salone.example/privacy')
            ->assertJsonPath('legal.terms_url', 'https://salone.example/termini')
            ->assertJsonPath('legal.support_url', 'https://salone.example/supporto');

        // Store metadata must be https: http is rejected.
        $this->putJson('/api/v1/manage/brand', [
            'privacy_policy_url' => 'http://insicuro.example/privacy',
        ], $this->authHeaders($admin, $env['tenant']))->assertStatus(422);
    }
}
