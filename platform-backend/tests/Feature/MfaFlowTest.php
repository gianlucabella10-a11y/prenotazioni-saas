<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Foundation\Auth\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class MfaFlowTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    public function test_enforced_admin_must_enroll_then_receives_full_session(): void
    {
        $env = $this->provisionBookableTenant();
        $admin = $this->createTenantAdmin($env['tenant'], mfaEnforced: true);

        // 1. Login yields no access token, only a setup challenge.
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'secret-password-123',
        ], $this->tenantKeyHeaders($env['tenant']))->assertOk();

        $login->assertJsonPath('mfa', 'setup_required')->assertJsonMissingPath('access_token');
        $mfaToken = $login->json('mfa_token');

        // 2. The MFA token cannot reach regular endpoints.
        $this->getJson('/api/v1/manage/services', [
            'Authorization' => "Bearer {$mfaToken}",
        ])->assertStatus(403)->assertJsonPath('error.code', 'mfa_required');

        // 3. Enrollment: obtain secret, confirm with a valid TOTP code.
        $setup = $this->postJson('/api/v1/auth/mfa/setup', [], [
            'Authorization' => "Bearer {$mfaToken}",
        ])->assertOk();

        $code = (new Totp())->codeForCounter($setup->json('secret'), intdiv(time(), 30));

        $session = $this->postJson('/api/v1/auth/mfa/confirm', ['code' => $code], [
            'Authorization' => "Bearer {$mfaToken}",
        ])->assertOk();

        $session->assertJsonStructure(['access_token', 'refresh_token']);

        // 4. The full token now reaches management endpoints.
        $this->getJson('/api/v1/manage/services', [
            'Authorization' => 'Bearer ' . $session->json('access_token'),
        ])->assertOk();
    }

    public function test_enrolled_admin_verifies_totp_at_each_login(): void
    {
        $env = $this->provisionBookableTenant();
        $admin = $this->createTenantAdmin($env['tenant'], mfaEnforced: true);

        $totp = new Totp();
        $secret = $totp->generateSecret();

        $this->bypassTenancy(fn () => $admin->mfaCredentials()->create([
            'type' => \App\Models\MfaCredential::TYPE_TOTP,
            'secret' => $secret,
            'confirmed_at' => now(),
        ]));

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'secret-password-123',
        ], $this->tenantKeyHeaders($env['tenant']))->assertOk();

        $login->assertJsonPath('mfa', 'verification_required');

        // Wrong code rejected…
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => '000000'], [
            'Authorization' => 'Bearer ' . $login->json('mfa_token'),
        ])->assertStatus(401)->assertJsonPath('error.code', 'invalid_mfa_code');

        // …valid code accepted.
        $code = $totp->codeForCounter($secret, intdiv(time(), 30));

        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $code], [
            'Authorization' => 'Bearer ' . $login->json('mfa_token'),
        ])->assertOk()->assertJsonStructure(['access_token']);
    }
}
