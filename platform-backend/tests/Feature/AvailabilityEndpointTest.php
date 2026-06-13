<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class AvailabilityEndpointTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    public function test_returns_slots_for_an_open_day(): void
    {
        $env = $this->provisionBookableTenant();
        $day = now()->addDays(3)->format('Y-m-d');

        $response = $this->getJson(
            '/api/v1/availability?' . http_build_query([
                'location_uuid' => $env['location']->uuid,
                'variant_uuids' => [$env['variant']->uuid],
                'from' => $day,
                'to' => $day,
            ]),
            $this->tenantKeyHeaders($env['tenant']),
        )->assertOk();

        $slots = $response->json("slots.{$day}");

        self::assertNotEmpty($slots);
        self::assertSame($env['staff']->uuid, $slots[0]['staff_uuid']);
        self::assertSame(30, $response->json('duration_minutes'));
    }

    public function test_booked_slot_disappears_from_availability(): void
    {
        Queue::fake();

        $env = $this->provisionBookableTenant();
        $actors = $this->createCustomerUser($env['tenant']);
        $day = now()->addDays(3)->format('Y-m-d');
        $start = now()->addDays(3)->setTime(10, 0)->format('Y-m-d\TH:i:s\Z');

        $query = http_build_query([
            'location_uuid' => $env['location']->uuid,
            'variant_uuids' => [$env['variant']->uuid],
            'from' => $day,
            'to' => $day,
        ]);

        $before = $this->getJson("/api/v1/availability?{$query}", $this->tenantKeyHeaders($env['tenant']))
            ->json("slots.{$day}.*.starts_at");

        self::assertContains($start, $before);

        $this->postJson('/api/v1/appointments', [
            'location_uuid' => $env['location']->uuid,
            'variant_uuids' => [$env['variant']->uuid],
            'staff_uuid' => $env['staff']->uuid,
            'starts_at' => $start,
        ], $this->authHeaders($actors['user'], $env['tenant']) + ['Idempotency-Key' => 'av-1'])
            ->assertCreated();

        // The write bumped the cache version (docs/33 #34): fresh data.
        $after = $this->getJson("/api/v1/availability?{$query}", $this->tenantKeyHeaders($env['tenant']))
            ->json("slots.{$day}.*.starts_at");

        self::assertNotContains($start, $after);
    }

    public function test_closure_exception_removes_the_day(): void
    {
        $env = $this->provisionBookableTenant();
        $day = now()->addDays(4)->format('Y-m-d');

        $this->bypassTenancy(fn () => \App\Modules\Scheduling\Infrastructure\Models\ScheduleException::query()->create([
            'tenant_id' => $env['tenant']->id,
            'scope' => 'staff',
            'staff_member_id' => $env['staff']->id,
            'date_start' => $day,
            'date_end' => $day,
            'kind' => 'closed',
            'reason' => 'Ferie',
        ]));

        $response = $this->getJson(
            '/api/v1/availability?' . http_build_query([
                'location_uuid' => $env['location']->uuid,
                'variant_uuids' => [$env['variant']->uuid],
                'from' => $day,
                'to' => $day,
            ]),
            $this->tenantKeyHeaders($env['tenant']),
        )->assertOk();

        self::assertNull($response->json("slots.{$day}"));
    }
}
