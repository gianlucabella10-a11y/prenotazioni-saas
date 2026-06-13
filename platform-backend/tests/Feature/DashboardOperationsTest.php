<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\Catalog\Infrastructure\Models\Service;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Infrastructure\Models\Appointment;
use App\Modules\Scheduling\Infrastructure\Models\ScheduleException;
use App\Modules\Staff\Infrastructure\Models\StaffMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Fase 9 — funzionalità della dashboard: CRUD servizi/operatori, orari,
 * eccezioni, azioni prenotazione, personalizzazione white label.
 */
final class DashboardOperationsTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function owner(array $env)
    {
        return $this->createTenantAdmin($env['tenant']);
    }

    public function test_service_crud_with_validation(): void
    {
        $env = $this->provisionBookableTenant();
        $owner = $this->owner($env);

        // Validazione: prezzo/durata/nome obbligatori.
        $this->actingAs($owner, 'web')
            ->post('/dashboard/servizi', ['name' => '', 'price' => -5])
            ->assertSessionHasErrors(['name', 'price', 'duration_minutes']);

        // Creazione.
        $this->actingAs($owner, 'web')->post('/dashboard/servizi', [
            'name' => 'Trattamento barba premium',
            'category' => 'Barba',
            'price' => '32.50',
            'duration_minutes' => 40,
            'buffer_after_minutes' => 5,
            'is_active' => 1,
        ])->assertRedirect('/dashboard/servizi');

        $this->bindTenant($env['tenant']);
        $service = Service::query()->where('name', 'Trattamento barba premium')->firstOrFail();
        $variant = $service->variants()->where('is_default', true)->firstOrFail();

        self::assertSame(3250, $variant->price_cents);
        self::assertSame(40, $variant->duration_minutes);

        // Modifica.
        $this->actingAs($owner, 'web')->put("/dashboard/servizi/{$service->uuid}", [
            'name' => 'Trattamento barba deluxe',
            'price' => '35.00',
            'duration_minutes' => 45,
        ])->assertRedirect('/dashboard/servizi');

        self::assertSame('Trattamento barba deluxe', $service->fresh()->name);
        self::assertSame(3500, $variant->fresh()->price_cents);

        // Eliminazione sicura = soft delete.
        $this->actingAs($owner, 'web')
            ->delete("/dashboard/servizi/{$service->uuid}")
            ->assertRedirect('/dashboard/servizi');

        self::assertSoftDeleted('services', ['id' => $service->id]);
    }

    public function test_staff_crud_with_weekly_schedule_bands(): void
    {
        $env = $this->provisionBookableTenant();
        $owner = $this->owner($env);

        $schedule = [];
        foreach (range(0, 6) as $weekday) {
            $schedule[$weekday] = [
                'morning' => ['start' => '', 'end' => ''],
                'afternoon' => ['start' => '', 'end' => ''],
            ];
        }
        // Martedì 09-13 / 15-19; mercoledì solo mattina.
        $schedule[1] = ['morning' => ['start' => '09:00', 'end' => '13:00'],
                        'afternoon' => ['start' => '15:00', 'end' => '19:00']];
        $schedule[2] = ['morning' => ['start' => '09:00', 'end' => '13:00'],
                        'afternoon' => ['start' => '', 'end' => '']];

        $this->actingAs($owner, 'web')->post('/dashboard/operatori', [
            'display_name' => 'Giulia',
            'role_label' => 'Hair stylist',
            'is_bookable' => 1,
            'service_ids' => [$env['service']->id],
            'schedule' => $schedule,
        ])->assertRedirect('/dashboard/operatori');

        $this->bindTenant($env['tenant']);
        $member = StaffMember::query()->where('display_name', 'Giulia')->firstOrFail();

        self::assertTrue($member->services->contains('id', $env['service']->id));
        self::assertSame(3, $member->schedules()->count()); // 2 fasce mar + 1 mer

        // Fascia invalida (fine < inizio) respinta.
        $bad = $schedule;
        $bad[3] = ['morning' => ['start' => '12:00', 'end' => '09:00'],
                   'afternoon' => ['start' => '', 'end' => '']];

        $this->actingAs($owner, 'web')->put("/dashboard/operatori/{$member->uuid}", [
            'display_name' => 'Giulia',
            'schedule' => $bad,
        ])->assertSessionHasErrors('schedule');

        // Disattivazione = soft delete.
        $this->actingAs($owner, 'web')
            ->delete("/dashboard/operatori/{$member->uuid}")
            ->assertRedirect('/dashboard/operatori');

        self::assertSoftDeleted('staff_members', ['id' => $member->id]);
    }

    public function test_exception_blocks_slots_through_the_real_engine(): void
    {
        $env = $this->provisionBookableTenant();
        $owner = $this->owner($env);
        $day = now()->addDays(5)->format('Y-m-d');

        // Prima: slot disponibili (motore reale via API pubblica).
        $before = $this->getJson(
            '/api/v1/availability?' . http_build_query([
                'location_uuid' => $env['location']->uuid,
                'variant_uuids' => [$env['variant']->uuid],
                'from' => $day, 'to' => $day,
            ]),
            $this->tenantKeyHeaders($env['tenant']),
        )->json("slots.$day");

        self::assertNotEmpty($before);

        // Ferie operatore dal pannello.
        $this->actingAs($owner, 'web')->post('/dashboard/disponibilita/chiusure', [
            'scope' => 'staff',
            'staff_uuid' => $env['staff']->uuid,
            'date_start' => $day,
            'date_end' => $day,
            'reason' => 'Ferie',
        ])->assertRedirect();

        // Dopo: nessuno slot — la dashboard usa il motore esistente, la
        // cache è stata invalidata (PLAN §3, nessun calcolo duplicato).
        $after = $this->getJson(
            '/api/v1/availability?' . http_build_query([
                'location_uuid' => $env['location']->uuid,
                'variant_uuids' => [$env['variant']->uuid],
                'from' => $day, 'to' => $day,
            ]),
            $this->tenantKeyHeaders($env['tenant']),
        )->json("slots.$day");

        self::assertEmpty($after ?? []);

        // Rimozione chiusura → slot tornano.
        $this->bindTenant($env['tenant']);
        $exception = ScheduleException::query()->firstOrFail();

        $this->actingAs($owner, 'web')
            ->delete("/dashboard/disponibilita/chiusure/{$exception->uuid}")
            ->assertRedirect();

        $restored = $this->getJson(
            '/api/v1/availability?' . http_build_query([
                'location_uuid' => $env['location']->uuid,
                'variant_uuids' => [$env['variant']->uuid],
                'from' => $day, 'to' => $day,
            ]),
            $this->tenantKeyHeaders($env['tenant']),
        )->json("slots.$day");

        self::assertNotEmpty($restored);
    }

    public function test_booking_lifecycle_actions_from_the_dashboard(): void
    {
        Queue::fake();

        $env = $this->provisionBookableTenant(tenantOverrides: [
            'settings' => [
                'reminder_offsets_hours' => [24],
                'booking_confirmation_mode' => 'request_approve',
            ],
        ]);
        $owner = $this->owner($env);
        $actors = $this->createCustomerUser($env['tenant']);

        // Il cliente invia una RICHIESTA via app (modalità approvazione).
        $start = now()->addDays(3)->setTime(10, 0)->format('Y-m-d\TH:i:s\Z');
        $uuid = $this->postJson('/api/v1/appointments', [
            'location_uuid' => $env['location']->uuid,
            'variant_uuids' => [$env['variant']->uuid],
            'staff_uuid' => $env['staff']->uuid,
            'starts_at' => $start,
        ], $this->authHeaders($actors['user'], $env['tenant']) + ['Idempotency-Key' => 'dash-1'])
            ->assertCreated()->json('data.uuid');

        // Appare tra le richieste in attesa…
        $day = now()->addDays(3)->format('Y-m-d');
        $this->actingAs($owner, 'web')
            ->get('/dashboard/prenotazioni?date=' . $day)
            ->assertOk()
            ->assertSee('in attesa di conferma');

        // …l'owner conferma (riusa TransitionAppointment).
        $this->actingAs($owner, 'web')
            ->post("/dashboard/prenotazioni/{$uuid}/conferma")
            ->assertRedirect();

        $this->bindTenant($env['tenant']);
        self::assertSame(
            AppointmentStatus::Confirmed,
            Appointment::query()->where('uuid', $uuid)->firstOrFail()->status,
        );

        // Annulla: il cliente viene notificato (outbox creato dal servizio
        // condiviso CancelAppointment).
        $this->actingAs($owner, 'web')
            ->post("/dashboard/prenotazioni/{$uuid}/annulla", ['reason' => 'Imprevisto'])
            ->assertRedirect();

        $appointment = Appointment::query()->where('uuid', $uuid)->firstOrFail();
        self::assertSame(AppointmentStatus::CancelledByTenant, $appointment->status);

        self::assertSame(
            1,
            \App\Modules\Notifications\Infrastructure\Models\NotificationRecord::query()
                ->where('template_code', 'booking_cancelled_by_tenant')->count(),
        );
    }

    public function test_brand_update_propagates_to_the_mobile_config(): void
    {
        $env = $this->provisionBookableTenant();
        $owner = $this->owner($env);

        $etagBefore = $this->getJson('/api/v1/app/config', $this->tenantKeyHeaders($env['tenant']))
            ->headers->get('ETag');

        $this->actingAs($owner, 'web')->put('/dashboard/personalizzazione/brand', [
            'app_name' => 'Dott. Verdi',
            'tagline' => 'Studio dentistico',
            'primary_color' => '#0B4F8A',
            'secondary_color' => '#C8A24B',
            'privacy_policy_url' => 'https://verdi.example/privacy',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // La config dell'app cliente riflette il nuovo brand (white label).
        $config = $this->getJson('/api/v1/app/config', $this->tenantKeyHeaders($env['tenant']) + [
            'If-None-Match' => $etagBefore,
        ])->assertOk();

        self::assertSame('Dott. Verdi', $config->json('app_name'));
        self::assertSame('#0B4F8A', $config->json('theme.colors.primary'));
        self::assertSame('https://verdi.example/privacy', $config->json('legal.privacy_policy_url'));

        // Contrasto insufficiente respinto (riusa ContrastValidator).
        $this->actingAs($owner, 'web')->put('/dashboard/personalizzazione/brand', [
            'app_name' => 'Dott. Verdi',
            'primary_color' => '#FEFEFE',
            'secondary_color' => '#C8A24B',
        ])->assertSessionHasErrors('primary_color');

        // Contatti → config (nome sede aggiornato + ETag bustato).
        $this->actingAs($owner, 'web')->put('/dashboard/personalizzazione/contatti', [
            'name' => 'Studio Verdi — Sede centrale',
            'phone' => '+39 02 1234567',
        ])->assertRedirect();

        $fresh = $this->getJson('/api/v1/app/config', $this->tenantKeyHeaders($env['tenant']));
        self::assertSame('Studio Verdi — Sede centrale', $fresh->json('locations.0.name'));
    }

    public function test_home_shows_operational_summary(): void
    {
        $env = $this->provisionBookableTenant();
        $owner = $this->owner($env);

        $this->actingAs($owner, 'web')
            ->get('/dashboard/home')
            ->assertOk()
            ->assertSee('Appuntamenti oggi')
            ->assertSee('In attesa di conferma')
            ->assertSee('Operatori attivi');
    }
}
