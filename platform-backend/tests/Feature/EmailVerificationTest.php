<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Foundation\Auth\EmailVerificationService;
use App\Models\User;
use App\Modules\Customers\Infrastructure\Models\Customer;
use App\Modules\Scheduling\Infrastructure\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * S1 security suite (MVP_PRODUCTION_READINESS_REPORT §6): registering an
 * email proves nothing; only the code delivered to that address does. CRM
 * linking — and with it the appointment history — happens exclusively
 * after verification.
 */
final class EmailVerificationTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function registerPayload(string $email = 'nuovo@example.com'): array
    {
        return [
            'email' => $email,
            'password' => 'password-sicura-123',
            'first_name' => 'Luca',
            'privacy_accepted' => true,
            'privacy_version' => '2026-01',
        ];
    }

    /** Extracts the 6-digit code from the last email on the array transport. */
    private function latestEmailedCode(): string
    {
        $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();

        self::assertNotEmpty($messages, 'No verification email was sent.');

        $body = $messages->last()->getOriginalMessage()->getTextBody();

        preg_match('/\b(\d{6})\b/', (string) $body, $matches);

        self::assertNotEmpty($matches, 'No code found in the email body.');

        return $matches[1];
    }

    public function test_registration_without_privacy_acceptance_is_rejected(): void
    {
        $env = $this->provisionBookableTenant();

        $payload = $this->registerPayload();
        unset($payload['privacy_accepted']);

        $this->postJson('/api/v1/auth/register', $payload, $this->tenantKeyHeaders($env['tenant']))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_registration_creates_unverified_user_without_crm_link_and_records_consent(): void
    {
        $env = $this->provisionBookableTenant();

        $response = $this->postJson(
            '/api/v1/auth/register',
            $this->registerPayload(),
            $this->tenantKeyHeaders($env['tenant']),
        )->assertCreated();

        $response->assertJsonPath('user.email_verified', false);

        $this->bindTenant($env['tenant']);

        // No customer record exists yet (S1: nothing to leak).
        self::assertSame(0, Customer::query()->where('email', 'nuovo@example.com')->count());

        // GDPR: privacy consent recorded with version and timestamp.
        $consent = DB::table('consents')->where('kind', 'privacy_policy')->first();
        self::assertNotNull($consent);
        self::assertSame('2026-01', $consent->document_version);
        self::assertNotNull($consent->occurred_at);
        self::assertTrue((bool) $consent->granted);
    }

    public function test_unverified_user_cannot_access_identity_bound_features(): void
    {
        $env = $this->provisionBookableTenant();

        $token = $this->postJson(
            '/api/v1/auth/register',
            $this->registerPayload(),
            $this->tenantKeyHeaders($env['tenant']),
        )->json('access_token');

        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/v1/appointments', $headers)
            ->assertStatus(403)->assertJsonPath('error.code', 'email_not_verified');

        $this->postJson('/api/v1/appointments', [], $headers)
            ->assertStatus(403)->assertJsonPath('error.code', 'email_not_verified');

        $this->putJson('/api/v1/me/devices', ['platform' => 'ios', 'fcm_token' => 'x'], $headers)
            ->assertStatus(403)->assertJsonPath('error.code', 'email_not_verified');

        $this->patchJson('/api/v1/me', ['first_name' => 'X'], $headers)
            ->assertStatus(403)->assertJsonPath('error.code', 'email_not_verified');

        // Account-level endpoints stay reachable (verification + deletion).
        $this->getJson('/api/v1/me', $headers)
            ->assertOk()->assertJsonPath('data.email_verified', false);
    }

    public function test_s1_attacker_with_someone_elses_email_never_reaches_their_history(): void
    {
        $env = $this->provisionBookableTenant();

        // The victim exists in the tenant CRM (created by staff) WITH an
        // appointment history.
        $victim = $this->bypassTenancy(function () use ($env) {
            $customer = Customer::factory()->create([
                'tenant_id' => $env['tenant']->id,
                'email' => 'vittima@example.com',
                'user_id' => null,
                'source' => 'staff',
            ]);

            Appointment::factory()->create([
                'tenant_id' => $env['tenant']->id,
                'customer_id' => $customer->id,
                'location_id' => $env['location']->id,
            ]);

            return $customer;
        });

        // The attacker registers the victim's email but cannot read the inbox.
        $token = $this->postJson(
            '/api/v1/auth/register',
            $this->registerPayload('vittima@example.com'),
            $this->tenantKeyHeaders($env['tenant']),
        )->json('access_token');

        // Appointments are unreachable…
        $this->getJson('/api/v1/appointments', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(403)->assertJsonPath('error.code', 'email_not_verified');

        // …guessing codes is bounded by attempts…
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/auth/email/verify', ['code' => '000000'], [
                'Authorization' => "Bearer {$token}",
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/email/verify', ['code' => '000000'], [
            'Authorization' => "Bearer {$token}",
        ])->assertStatus(401)->assertJsonPath('error.code', 'too_many_verification_attempts');

        // …and the CRM record was never linked.
        self::assertNull($victim->fresh()->user_id);
    }

    public function test_legitimate_owner_verifies_and_inherits_their_crm_history(): void
    {
        $env = $this->provisionBookableTenant();

        $existing = $this->bypassTenancy(function () use ($env) {
            $customer = Customer::factory()->create([
                'tenant_id' => $env['tenant']->id,
                'email' => 'cliente@example.com',
                'user_id' => null,
                'source' => 'staff',
            ]);

            Appointment::factory()->create([
                'tenant_id' => $env['tenant']->id,
                'customer_id' => $customer->id,
                'location_id' => $env['location']->id,
            ]);

            return $customer;
        });

        $token = $this->postJson(
            '/api/v1/auth/register',
            $this->registerPayload('cliente@example.com'),
            $this->tenantKeyHeaders($env['tenant']),
        )->json('access_token');

        $code = $this->latestEmailedCode();

        $this->postJson('/api/v1/auth/email/verify', ['code' => $code], [
            'Authorization' => "Bearer {$token}",
        ])->assertOk()->assertJsonPath('email_verified', true);

        // Ownership proven → CRM linked → history visible.
        self::assertNotNull($existing->fresh()->user_id);

        $appointments = $this->getJson('/api/v1/appointments?scope=upcoming', [
            'Authorization' => "Bearer {$token}",
        ])->assertOk()->json('data');

        self::assertCount(1, $appointments);
    }

    public function test_wrong_expired_and_reused_codes_all_fail(): void
    {
        $env = $this->provisionBookableTenant();

        $token = $this->postJson(
            '/api/v1/auth/register',
            $this->registerPayload(),
            $this->tenantKeyHeaders($env['tenant']),
        )->json('access_token');

        $headers = ['Authorization' => "Bearer {$token}"];
        $code = $this->latestEmailedCode();

        // Wrong code.
        $wrong = $code === '111111' ? '222222' : '111111';
        $this->postJson('/api/v1/auth/email/verify', ['code' => $wrong], $headers)
            ->assertStatus(401)->assertJsonPath('error.code', 'invalid_verification_code');

        // Expired code.
        DB::table('email_verifications')->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/v1/auth/email/verify', ['code' => $code], $headers)
            ->assertStatus(401)->assertJsonPath('error.code', 'verification_code_expired');

        // Resend issues a fresh one; the old code no longer works.
        $this->postJson('/api/v1/auth/email/resend', [], $headers)->assertOk();
        $fresh = $this->latestEmailedCode();

        if ($fresh !== $code) {
            $this->postJson('/api/v1/auth/email/verify', ['code' => $code], $headers)
                ->assertStatus(401);
        }

        // The fresh code verifies…
        $this->postJson('/api/v1/auth/email/verify', ['code' => $fresh], $headers)
            ->assertOk();

        // …and is single-use: replaying it fails.
        $this->postJson('/api/v1/auth/email/verify', ['code' => $fresh], $headers)
            ->assertStatus(422)->assertJsonPath('error.code', 'already_verified');
    }

    public function test_cross_user_code_is_useless(): void
    {
        $env = $this->provisionBookableTenant();

        $tokenA = $this->postJson(
            '/api/v1/auth/register',
            $this->registerPayload('utente-a@example.com'),
            $this->tenantKeyHeaders($env['tenant']),
        )->json('access_token');

        $codeA = $this->latestEmailedCode();

        $tokenB = $this->postJson(
            '/api/v1/auth/register',
            $this->registerPayload('utente-b@example.com'),
            $this->tenantKeyHeaders($env['tenant']),
        )->json('access_token');

        // B tries to use A's code: hash is bound to the user uuid → fails.
        $this->postJson('/api/v1/auth/email/verify', ['code' => $codeA], [
            'Authorization' => "Bearer {$tokenB}",
        ])->assertStatus(401);

        // A's own code still works for A.
        $this->postJson('/api/v1/auth/email/verify', ['code' => $codeA], [
            'Authorization' => "Bearer {$tokenA}",
        ])->assertOk();
    }

    public function test_verification_without_existing_crm_creates_customer_from_registration_data(): void
    {
        $env = $this->provisionBookableTenant();

        $token = $this->postJson(
            '/api/v1/auth/register',
            $this->registerPayload(),
            $this->tenantKeyHeaders($env['tenant']),
        )->json('access_token');

        $this->postJson('/api/v1/auth/email/verify', ['code' => $this->latestEmailedCode()], [
            'Authorization' => "Bearer {$token}",
        ])->assertOk();

        $this->bindTenant($env['tenant']);

        $customer = Customer::query()->where('email', 'nuovo@example.com')->first();

        self::assertNotNull($customer);
        self::assertSame('Luca', $customer->first_name);
        self::assertNotNull($customer->user_id);

        // Idempotency: a direct second verify-link cannot duplicate.
        $user = User::query()->whereKey($customer->user_id)->firstOrFail();
        app(EmailVerificationService::class)->issue($user);
        self::assertSame(1, Customer::query()->where('email', 'nuovo@example.com')->count());
    }
}
