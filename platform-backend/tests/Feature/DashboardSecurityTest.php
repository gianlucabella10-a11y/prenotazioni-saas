<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Foundation\Auth\Totp;
use App\Models\MfaCredential;
use App\Models\User;
use App\Modules\Scheduling\Infrastructure\Models\Appointment;
use App\Modules\Staff\Infrastructure\Models\StaffMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Fase 8 — sicurezza della dashboard: autenticazione (invito, login, MFA),
 * isolamento tenant sulla superficie web, confini OWNER/STAFF.
 */
final class DashboardSecurityTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard/home')->assertRedirect('/dashboard/login');
        $this->get('/dashboard/servizi')->assertRedirect('/dashboard/login');
    }

    public function test_invite_sets_password_and_enforced_mfa_setup_gates_login(): void
    {
        $env = $this->provisionBookableTenant();

        // Owner come dal provisioning reale: MFA enforced, nessuna password.
        $owner = $this->bypassTenancy(fn (): User => User::factory()
            ->tenantAdmin(true)
            ->create([
                'tenant_id' => $env['tenant']->id,
                'password' => null,
                'email' => 'titolare@salone.it',
            ]));

        $inviteToken = 'token-invito-di-test';
        DB::table('password_reset_tokens')->insert([
            'email' => $owner->email,
            'token' => hash('sha256', $inviteToken),
            'created_at' => now(),
        ]);

        // 1. Accettazione invito.
        $this->post('/dashboard/invito', [
            'email' => $owner->email,
            'token' => $inviteToken,
            'password' => 'password-titolare-1',
            'password_confirmation' => 'password-titolare-1',
        ])->assertRedirect('/dashboard/login');

        // Token monouso.
        self::assertSame(0, DB::table('password_reset_tokens')->where('email', $owner->email)->count());

        // 2. Login → setup MFA obbligatorio (docs/14 §2), non sessione.
        $this->post('/dashboard/login', [
            'email' => $owner->email,
            'password' => 'password-titolare-1',
        ])->assertRedirect('/dashboard/mfa/setup');

        $this->assertGuest('web');

        // 3. Setup pagina genera il secret…
        $this->get('/dashboard/mfa/setup')->assertOk()->assertSee('authenticator');

        $secret = MfaCredential::query()->where('user_id', $owner->id)->firstOrFail()->secret;

        // …codice sbagliato rifiutato…
        $this->post('/dashboard/mfa/setup', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest('web');

        // …codice TOTP valido completa l'accesso.
        $code = (new Totp())->codeForCounter($secret, intdiv(time(), 30));

        $this->post('/dashboard/mfa/setup', ['code' => $code])
            ->assertRedirect('/dashboard/home');

        $this->assertAuthenticatedAs($owner->fresh(), 'web');

        // 4. Ai login successivi: challenge, non setup.
        $this->post('/dashboard/logout');
        $this->post('/dashboard/login', [
            'email' => $owner->email,
            'password' => 'password-titolare-1',
        ])->assertRedirect('/dashboard/mfa');
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $env = $this->provisionBookableTenant();
        $this->createTenantAdmin($env['tenant']);

        $this->post('/dashboard/login', [
            'email' => 'inesistente@example.com',
            'password' => 'qualunque-cosa',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_customers_cannot_enter_the_dashboard(): void
    {
        $env = $this->provisionBookableTenant();
        $actors = $this->createCustomerUser($env['tenant']);

        $this->actingAs($actors['user'], 'web')
            ->get('/dashboard/home')
            ->assertRedirect('/dashboard/login');
    }

    public function test_tenant_isolation_on_the_web_surface(): void
    {
        $tenantA = $this->provisionBookableTenant();
        $tenantB = $this->provisionBookableTenant();

        $this->bypassTenancy(fn () => $tenantB['service']->update(['name' => 'SEGRETO-DI-B']));

        $ownerA = $this->createTenantAdmin($tenantA['tenant']);

        // La lista servizi di A non contiene il servizio di B…
        $this->actingAs($ownerA, 'web')
            ->get('/dashboard/servizi')
            ->assertOk()
            ->assertDontSee('SEGRETO-DI-B');

        // …e gli uuid di B rispondono 404, mai 403 (docs/28 §2 livello 5).
        $this->actingAs($ownerA, 'web')
            ->get('/dashboard/servizi/' . $tenantB['service']->uuid)
            ->assertNotFound();

        $this->actingAs($ownerA, 'web')
            ->put('/dashboard/servizi/' . $tenantB['service']->uuid, [
                'name' => 'Hijack', 'price' => 10, 'duration_minutes' => 30,
            ])->assertNotFound();

        self::assertSame(
            'SEGRETO-DI-B',
            $this->bypassTenancy(fn () => $tenantB['service']->fresh()->name),
        );
    }

    public function test_staff_sees_only_own_agenda_and_no_configuration(): void
    {
        $env = $this->provisionBookableTenant();

        // Due operatori con utenti collegati; un appuntamento ciascuno.
        [$staffUserA, $memberA] = $this->staffWithUser($env, 'staff-a@salone.it');
        [, $memberB] = $this->staffWithUser($env, 'staff-b@salone.it');

        $apptA = $this->appointmentFor($env, $memberA, 10);
        $apptB = $this->appointmentFor($env, $memberB, 11);

        // Config: vietata (403).
        $this->actingAs($staffUserA, 'web')->get('/dashboard/servizi')->assertForbidden();
        $this->actingAs($staffUserA, 'web')->get('/dashboard/personalizzazione')->assertForbidden();

        // Agenda: vede A, non vede B.
        $day = now()->addDays(2)->format('Y-m-d');
        $response = $this->actingAs($staffUserA, 'web')
            ->get('/dashboard/prenotazioni?date=' . $day)
            ->assertOk();

        $response->assertSee($apptA->customer->first_name);
        $response->assertDontSee($apptB->customer->first_name);

        // Azioni sull'appuntamento di B: 404.
        $this->actingAs($staffUserA, 'web')
            ->post("/dashboard/prenotazioni/{$apptB->uuid}/completa")
            ->assertNotFound();

        // OWNER invece vede entrambi.
        $owner = $this->createTenantAdmin($env['tenant']);
        $all = $this->actingAs($owner, 'web')
            ->get('/dashboard/prenotazioni?date=' . $day)
            ->assertOk();

        $all->assertSee($apptA->customer->first_name);
        $all->assertSee($apptB->customer->first_name);
    }

    public function test_staff_without_agenda_profile_is_blocked_not_unscoped(): void
    {
        $env = $this->provisionBookableTenant();

        $orphanStaff = $this->bypassTenancy(fn (): User => User::factory()->staff()
            ->create(['tenant_id' => $env['tenant']->id]));

        $this->actingAs($orphanStaff, 'web')
            ->get('/dashboard/prenotazioni')
            ->assertForbidden();
    }

    /** @return array{0: User, 1: StaffMember} */
    private function staffWithUser(array $env, string $email): array
    {
        return $this->bypassTenancy(function () use ($env, $email): array {
            $user = User::factory()->staff()->create([
                'tenant_id' => $env['tenant']->id,
                'email' => $email,
            ]);

            $member = StaffMember::factory()->create([
                'tenant_id' => $env['tenant']->id,
                'user_id' => $user->id,
            ]);

            return [$user, $member];
        });
    }

    private function appointmentFor(array $env, StaffMember $member, int $hour): Appointment
    {
        return $this->bypassTenancy(function () use ($env, $member, $hour): Appointment {
            $appointment = Appointment::factory()->create([
                'tenant_id' => $env['tenant']->id,
                'customer_id' => \App\Modules\Customers\Infrastructure\Models\Customer::factory()->create([
                    'tenant_id' => $env['tenant']->id,
                ])->id,
                'location_id' => $env['location']->id,
                'starts_at' => now()->addDays(2)->setTime($hour, 0),
                'ends_at' => now()->addDays(2)->setTime($hour, 30),
            ]);

            $appointment->items()->create([
                'tenant_id' => $env['tenant']->id,
                'service_variant_id' => $env['variant']->id,
                'staff_member_id' => $member->id,
                'service_name_snapshot' => $env['service']->name,
                'variant_name_snapshot' => 'Standard',
                'duration_minutes_snapshot' => 30,
                'buffer_minutes_snapshot' => 0,
                'price_cents_snapshot' => 1800,
                'starts_at' => $appointment->starts_at,
                'ends_at' => $appointment->ends_at,
                'is_blocking' => 1,
            ]);

            return $appointment;
        });
    }
}
