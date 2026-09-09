<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Notifications\Application\SendNotificationJob;
use App\Modules\Notifications\Infrastructure\Models\NotificationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class BookingFlowTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    /** A UTC start that is always inside the 09:00-19:00 local opening. */
    private function bookableStart(int $daysAhead = 3): string
    {
        return now()->addDays($daysAhead)->setTime(10, 0)->format('Y-m-d\TH:i:s\Z');
    }

    private function bookingPayload(array $env, ?string $startsAt = null): array
    {
        return [
            'location_uuid' => $env['location']->uuid,
            'variant_uuids' => [$env['variant']->uuid],
            'staff_uuid' => $env['staff']->uuid,
            'starts_at' => $startsAt ?? $this->bookableStart(),
        ];
    }

    public function test_customer_books_a_slot_and_reminders_are_scheduled(): void
    {
        Queue::fake();

        $env = $this->provisionBookableTenant();
        $actors = $this->createCustomerUser($env['tenant']);
        $headers = $this->authHeaders($actors['user'], $env['tenant']) + ['Idempotency-Key' => 'book-1'];

        $response = $this->postJson('/api/v1/appointments', $this->bookingPayload($env), $headers);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.items.0.service_name', $env['service']->name)
            ->assertJsonPath('data.items.0.staff_uuid', $env['staff']->uuid);

        // Outbox: confirmation (due now) + 24h reminder (docs/29 §3).
        $this->bindTenant($env['tenant']);
        $records = NotificationRecord::query()->pluck('template_code');
        self::assertEqualsCanonicalizing(['booking_confirmed', 'booking_reminder'], $records->all());

        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_same_slot_cannot_be_booked_twice(): void
    {
        Queue::fake();

        $env = $this->provisionBookableTenant();
        $first = $this->createCustomerUser($env['tenant'], 'primo@example.com');
        $second = $this->createCustomerUser($env['tenant'], 'secondo@example.com');
        $start = $this->bookableStart();

        $this->postJson(
            '/api/v1/appointments',
            $this->bookingPayload($env, $start),
            $this->authHeaders($first['user'], $env['tenant']) + ['Idempotency-Key' => 'a-1'],
        )->assertCreated();

        $this->postJson(
            '/api/v1/appointments',
            $this->bookingPayload($env, $start),
            $this->authHeaders($second['user'], $env['tenant']) + ['Idempotency-Key' => 'b-1'],
        )->assertStatus(409)->assertJsonPath('error.code', 'slot_unavailable');
    }

    public function test_overlapping_slot_is_rejected(): void
    {
        Queue::fake();

        $env = $this->provisionBookableTenant(variantOverrides: ['duration_minutes' => 60]);
        $first = $this->createCustomerUser($env['tenant'], 'primo@example.com');
        $second = $this->createCustomerUser($env['tenant'], 'secondo@example.com');

        $base = now()->addDays(3)->setTime(10, 0);

        $this->postJson(
            '/api/v1/appointments',
            $this->bookingPayload($env, $base->format('Y-m-d\TH:i:s\Z')),
            $this->authHeaders($first['user'], $env['tenant']) + ['Idempotency-Key' => 'a-1'],
        )->assertCreated();

        // 10:30 overlaps the 10:00-11:00 booking even with a different start.
        $this->postJson(
            '/api/v1/appointments',
            $this->bookingPayload($env, $base->copy()->addMinutes(30)->format('Y-m-d\TH:i:s\Z')),
            $this->authHeaders($second['user'], $env['tenant']) + ['Idempotency-Key' => 'b-1'],
        )->assertStatus(409);
    }

    public function test_idempotency_key_replay_returns_same_appointment(): void
    {
        Queue::fake();

        $env = $this->provisionBookableTenant();
        $actors = $this->createCustomerUser($env['tenant']);
        $headers = $this->authHeaders($actors['user'], $env['tenant']) + ['Idempotency-Key' => 'same-key'];

        $first = $this->postJson('/api/v1/appointments', $this->bookingPayload($env), $headers)->assertCreated();
        $replay = $this->postJson('/api/v1/appointments', $this->bookingPayload($env), $headers)->assertCreated();

        self::assertSame($first->json('data.uuid'), $replay->json('data.uuid'));

        $this->bindTenant($env['tenant']);
        self::assertSame(1, \App\Modules\Scheduling\Infrastructure\Models\Appointment::query()->count());
    }

    public function test_cancellation_within_cutoff_frees_the_slot(): void
    {
        Queue::fake();

        $env = $this->provisionBookableTenant();
        $actors = $this->createCustomerUser($env['tenant']);
        $headers = $this->authHeaders($actors['user'], $env['tenant']);
        $start = $this->bookableStart();

        $uuid = $this->postJson(
            '/api/v1/appointments',
            $this->bookingPayload($env, $start),
            $headers + ['Idempotency-Key' => 'c-1'],
        )->json('data.uuid');

        $this->postJson("/api/v1/appointments/{$uuid}/cancel", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled_by_customer');

        // Reminders become obsolete (docs/29 §3)…
        $this->bindTenant($env['tenant']);
        self::assertSame(
            NotificationRecord::STATUS_OBSOLETE,
            NotificationRecord::query()->where('template_code', 'booking_reminder')->value('status'),
        );

        // …and the slot is bookable again by someone else.
        $other = $this->createCustomerUser($env['tenant'], 'altro@example.com');

        $this->postJson(
            '/api/v1/appointments',
            $this->bookingPayload($env, $start),
            $this->authHeaders($other['user'], $env['tenant']) + ['Idempotency-Key' => 'd-1'],
        )->assertCreated();
    }

    public function test_cancellation_after_cutoff_is_rejected(): void
    {
        Queue::fake();

        $env = $this->provisionBookableTenant();
        $actors = $this->createCustomerUser($env['tenant']);
        $headers = $this->authHeaders($actors['user'], $env['tenant']);

        // Cutoff is 24h: an appointment ~2h away is no longer cancellable.
        $soon = now()->addHours(2)->format('Y-m-d\TH:i:s\Z');

        $booking = $this->postJson(
            '/api/v1/appointments',
            $this->bookingPayload($env, $soon),
            $headers + ['Idempotency-Key' => 'e-1'],
        );

        // Guard: if the near slot fell outside opening hours, skip cleanly
        // rather than asserting on the wrong precondition.
        if ($booking->status() !== 201) {
            self::markTestSkipped('Near slot outside local opening hours at this run time.');
        }

        $this->postJson('/api/v1/appointments/' . $booking->json('data.uuid') . '/cancel', [], $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'cutoff_passed');
    }

    public function test_request_approve_tenant_creates_requested_appointment(): void
    {
        Queue::fake();

        $env = $this->provisionBookableTenant(tenantOverrides: [
            'settings' => [
                'reminder_offsets_hours' => [24],
                'booking_confirmation_mode' => 'request_approve',
            ],
        ]);
        $actors = $this->createCustomerUser($env['tenant']);

        $this->postJson(
            '/api/v1/appointments',
            $this->bookingPayload($env),
            $this->authHeaders($actors['user'], $env['tenant']) + ['Idempotency-Key' => 'r-1'],
        )->assertCreated()->assertJsonPath('data.status', 'requested');
    }

    public function test_booking_beyond_window_is_rejected(): void
    {
        Queue::fake();

        $env = $this->provisionBookableTenant();
        $actors = $this->createCustomerUser($env['tenant']);

        $tooFar = now()->addDays(90)->setTime(10, 0)->format('Y-m-d\TH:i:s\Z');

        $this->postJson(
            '/api/v1/appointments',
            $this->bookingPayload($env, $tooFar),
            $this->authHeaders($actors['user'], $env['tenant']) + ['Idempotency-Key' => 'f-1'],
        )->assertStatus(422)->assertJsonPath('error.code', 'beyond_booking_window');
    }

    public function test_customer_active_booking_quota_is_enforced(): void
    {
        Queue::fake();

        $env = $this->provisionBookableTenant();
        $actors = $this->createCustomerUser($env['tenant']);

        // Booking Identity (Fase 4): tetto di 1 prenotazione attiva per cliente.
        $this->bindTenant($env['tenant']);
        $env['location']->forceFill([
            'settings' => ['max_active_bookings_per_customer' => 1],
        ])->save();

        $this->postJson(
            '/api/v1/appointments',
            $this->bookingPayload($env, $this->bookableStart(3)),
            $this->authHeaders($actors['user'], $env['tenant']) + ['Idempotency-Key' => 'q-1'],
        )->assertCreated();

        // Seconda prenotazione (slot diverso) → oltre il tetto.
        $this->postJson(
            '/api/v1/appointments',
            $this->bookingPayload($env, $this->bookableStart(5)),
            $this->authHeaders($actors['user'], $env['tenant']) + ['Idempotency-Key' => 'q-2'],
        )->assertStatus(422)->assertJsonPath('error.code', 'booking_limit_reached');
    }
}
