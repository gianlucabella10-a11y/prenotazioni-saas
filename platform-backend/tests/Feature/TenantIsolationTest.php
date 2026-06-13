<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * RNF-21 (docs/05, docs/28 §2 level 5): no endpoint may read, write or
 * enumerate another tenant's data — known uuids must answer 404, not 403.
 */
final class TenantIsolationTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    public function test_catalog_listing_never_leaks_other_tenants_services(): void
    {
        $tenantA = $this->provisionBookableTenant();
        $tenantB = $this->provisionBookableTenant();

        $this->bypassTenancy(function () use ($tenantB): void {
            $tenantB['service']->update(['name' => 'SERVIZIO-RISERVATO-B']);
        });

        $response = $this->getJson('/api/v1/catalog/services', $this->tenantKeyHeaders($tenantA['tenant']))
            ->assertOk();

        self::assertStringNotContainsString('SERVIZIO-RISERVATO-B', $response->getContent());
    }

    public function test_customer_cannot_read_another_tenants_appointment_by_uuid(): void
    {
        Queue::fake();

        $tenantA = $this->provisionBookableTenant();
        $tenantB = $this->provisionBookableTenant();

        $actorsB = $this->createCustomerUser($tenantB['tenant'], 'b@example.com');
        $start = now()->addDays(3)->setTime(10, 0)->format('Y-m-d\TH:i:s\Z');

        $uuidB = $this->postJson('/api/v1/appointments', [
            'location_uuid' => $tenantB['location']->uuid,
            'variant_uuids' => [$tenantB['variant']->uuid],
            'staff_uuid' => $tenantB['staff']->uuid,
            'starts_at' => $start,
        ], $this->authHeaders($actorsB['user'], $tenantB['tenant']) + ['Idempotency-Key' => 'iso-1'])
            ->assertCreated()
            ->json('data.uuid');

        // A tenant-A customer probing tenant-B's appointment uuid: 404.
        $actorsA = $this->createCustomerUser($tenantA['tenant'], 'a@example.com');

        $this->getJson("/api/v1/appointments/{$uuidB}", $this->authHeaders($actorsA['user'], $tenantA['tenant']))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_booking_against_another_tenants_resources_fails_as_not_found(): void
    {
        Queue::fake();

        $tenantA = $this->provisionBookableTenant();
        $tenantB = $this->provisionBookableTenant();
        $actorsA = $this->createCustomerUser($tenantA['tenant'], 'a@example.com');

        $this->postJson('/api/v1/appointments', [
            'location_uuid' => $tenantB['location']->uuid, // foreign location!
            'variant_uuids' => [$tenantA['variant']->uuid],
            'staff_uuid' => $tenantA['staff']->uuid,
            'starts_at' => now()->addDays(3)->setTime(10, 0)->format('Y-m-d\TH:i:s\Z'),
        ], $this->authHeaders($actorsA['user'], $tenantA['tenant']) + ['Idempotency-Key' => 'iso-2'])
            ->assertStatus(404);
    }

    public function test_manage_endpoints_cannot_touch_foreign_resources(): void
    {
        $tenantA = $this->provisionBookableTenant();
        $tenantB = $this->provisionBookableTenant();
        $adminA = $this->createTenantAdmin($tenantA['tenant']);

        // Updating tenant B's service through tenant A's admin: 404.
        $this->patchJson(
            '/api/v1/manage/services/' . $tenantB['service']->uuid,
            ['name' => 'Hijacked'],
            $this->authHeaders($adminA, $tenantA['tenant']),
        )->assertStatus(404);

        self::assertNotSame(
            'Hijacked',
            $this->bypassTenancy(fn () => $tenantB['service']->fresh()->name),
        );

        // Staff listing only contains tenant A staff.
        $staffList = $this->getJson('/api/v1/manage/staff', $this->authHeaders($adminA, $tenantA['tenant']))
            ->assertOk()
            ->json('data.*.uuid');

        self::assertContains($tenantA['staff']->uuid, $staffList);
        self::assertNotContains($tenantB['staff']->uuid, $staffList);
    }

    public function test_jwt_for_one_tenant_is_useless_with_another_tenants_key(): void
    {
        $tenantA = $this->provisionBookableTenant();
        $tenantB = $this->provisionBookableTenant();
        $actorsA = $this->createCustomerUser($tenantA['tenant'], 'a@example.com');

        // Token of tenant A presented alongside tenant B's public key: the
        // signed claim wins, the request still only sees tenant A data —
        // here the appointment list is simply empty, never tenant B's.
        $response = $this->getJson('/api/v1/appointments', [
            'Authorization' => 'Bearer ' . $this->accessTokenFor($actorsA['user']),
            'X-Tenant-Key' => $tenantB['tenant']->api_key,
            'Accept' => 'application/json',
        ])->assertOk();

        self::assertSame([], $response->json('data'));
    }
}
