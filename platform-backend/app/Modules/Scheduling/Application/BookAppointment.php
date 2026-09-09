<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Foundation\Http\ApiException;
use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\Catalog\Infrastructure\Models\Location;
use App\Modules\Catalog\Infrastructure\Models\ServiceVariant;
use App\Modules\Customers\Infrastructure\Models\Customer;
use App\Modules\Scheduling\Application\Events\AppointmentBooked;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Domain\Exceptions\SlotUnavailable;
use App\Modules\Scheduling\Domain\TimeInterval;
use App\Modules\Scheduling\Infrastructure\Models\Appointment;
use App\Modules\Scheduling\Infrastructure\Models\AppointmentItem;
use App\Modules\Staff\Infrastructure\Models\StaffMember;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;

/**
 * Atomic booking (docs/30 §4, RNF-20):
 *
 *  1. resolve and validate input (variants, staff, policy windows)
 *  2. inside ONE transaction: SELECT … FOR UPDATE on the staff's blocking
 *     items in the day, verify no overlap, insert appointment + items
 *  3. the UNIQUE(tenant, staff, starts_at, is_blocking) index is the final
 *     safety net against identical concurrent starts
 *  4. domain event after commit → notifications, cache invalidation
 *
 * Idempotency: a replayed Idempotency-Key returns the original appointment.
 */
final readonly class BookAppointment
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private Connection $db,
        private Dispatcher $events,
        private AvailabilityCacheVersion $cacheVersion,
    ) {
    }

    /**
     * @param list<string> $variantUuids services chained in order (docs/30 §6)
     */
    public function execute(
        Customer $customer,
        string $locationUuid,
        array $variantUuids,
        string $staffUuid,
        string $startsAtIso,
        string $idempotencyKey,
        string $source = 'app',
    ): Appointment {
        $existing = Appointment::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return $existing; // idempotent replay (docs/25 §6)
        }

        $location = Location::query()->where('uuid', $locationUuid)->firstOrFail();
        $staff = StaffMember::query()->where('uuid', $staffUuid)->where('is_bookable', true)->firstOrFail();
        $variants = $this->loadVariants($variantUuids, $staff);

        $startsAt = $this->parseStart($startsAtIso);
        $this->assertWithinBookingPolicy($location, $startsAt);
        $this->assertWithinCustomerQuota($customer, $location);

        $tenant = $this->currentTenant->get();
        $status = $tenant->requiresBookingApproval() ? AppointmentStatus::Requested : AppointmentStatus::Confirmed;

        $appointment = $this->db->transaction(function () use (
            $customer, $location, $staff, $variants, $startsAt, $idempotencyKey, $status, $source
        ): Appointment {
            $itemsPlan = $this->planItems($variants, $staff, $startsAt);
            $total = new TimeInterval($startsAt, $itemsPlan[count($itemsPlan) - 1]['ends_at']);

            $this->assertIntervalFree($staff, $total);

            $appointment = Appointment::query()->create([
                'customer_id' => $customer->id,
                'location_id' => $location->id,
                'status' => $status,
                'starts_at' => $total->start,
                'ends_at' => $total->end,
                'total_price_cents' => array_sum(array_column($itemsPlan, 'price_cents')),
                'currency' => $variants->first()->currency,
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
                'confirmed_at' => $status === AppointmentStatus::Confirmed ? now() : null,
            ]);

            foreach ($itemsPlan as $position => $plan) {
                $appointment->items()->create([
                    'tenant_id' => $appointment->tenant_id,
                    'service_variant_id' => $plan['variant']->id,
                    'staff_member_id' => $staff->id,
                    'service_name_snapshot' => $plan['variant']->service->name,
                    'variant_name_snapshot' => $plan['variant']->name,
                    'duration_minutes_snapshot' => $plan['variant']->duration_minutes,
                    'buffer_minutes_snapshot' => $plan['variant']->buffer_after_minutes,
                    'price_cents_snapshot' => $plan['price_cents'],
                    'starts_at' => $plan['starts_at'],
                    'ends_at' => $plan['ends_at'],
                    'position' => $position,
                    'is_blocking' => 1,
                ]);
            }

            $appointment->events()->create([
                'tenant_id' => $appointment->tenant_id,
                'from_status' => null,
                'to_status' => $status->value,
                'actor_type' => $source === 'app' ? 'customer' : 'staff',
                'actor_id' => $customer->id,
                'created_at' => now(),
            ]);

            return $appointment;
        });

        $this->invalidateAvailability($staff->id, $location, $appointment);

        $this->events->dispatch(new AppointmentBooked($appointment->id, $appointment->tenant_id));

        return $appointment;
    }

    /** @return Collection<int, ServiceVariant> */
    private function loadVariants(array $variantUuids, StaffMember $staff): Collection
    {
        if ($variantUuids === []) {
            throw ApiException::unprocessable('no_services_selected', 'Select at least one service.');
        }

        $found = ServiceVariant::query()
            ->with('service')
            ->whereIn('uuid', $variantUuids)
            ->where('is_active', true)
            ->get()
            ->keyBy('uuid');

        $enabledServiceIds = $staff->services()->pluck('services.id');

        $ordered = collect($variantUuids)->map(function (string $uuid) use ($found, $enabledServiceIds): ServiceVariant {
            $variant = $found->get($uuid);

            if ($variant === null) {
                throw ApiException::unprocessable('unknown_service_variant', 'One or more selected services are not available.');
            }

            if (! $enabledServiceIds->contains($variant->service_id)) {
                throw ApiException::unprocessable('staff_not_enabled', 'The selected staff member does not perform this service.');
            }

            return $variant;
        });

        return $ordered->values();
    }

    private function parseStart(string $iso): DateTimeImmutable
    {
        $start = new DateTimeImmutable($iso);

        return $start->setTimezone(new DateTimeZone('UTC'));
    }

    private function assertWithinBookingPolicy(Location $location, DateTimeImmutable $startsAt): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($startsAt < $now->modify("+{$location->min_notice_minutes} minutes")) {
            throw ApiException::unprocessable('too_late_to_book', 'This time can no longer be booked.');
        }

        if ($startsAt > $now->modify("+{$location->booking_window_days} days")) {
            throw ApiException::unprocessable('beyond_booking_window', 'This date is not yet open for booking.');
        }
    }

    /**
     * Booking Identity (Fase 4): tetto opzionale di prenotazioni attive per
     * cliente (`settings.max_active_bookings_per_customer`, 0/assente =
     * illimitato). Conta gli appuntamenti futuri ancora richiesti/confermati.
     */
    private function assertWithinCustomerQuota(Customer $customer, Location $location): void
    {
        $max = (int) ($location->settings['max_active_bookings_per_customer'] ?? 0);

        if ($max <= 0) {
            return;
        }

        $active = Appointment::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', [
                AppointmentStatus::Requested->value,
                AppointmentStatus::Confirmed->value,
            ])
            ->where('starts_at', '>=', now())
            ->count();

        if ($active >= $max) {
            throw ApiException::unprocessable(
                'booking_limit_reached',
                'You have reached the maximum number of active bookings.',
                ['max' => $max],
            );
        }
    }

    /**
     * Chain the variants back-to-back from the requested start.
     *
     * @param Collection<int, ServiceVariant> $variants
     *
     * @return list<array{variant: ServiceVariant, starts_at: DateTimeImmutable, ends_at: DateTimeImmutable, price_cents: int}>
     */
    private function planItems(Collection $variants, StaffMember $staff, DateTimeImmutable $startsAt): array
    {
        $plan = [];
        $cursor = $startsAt;

        foreach ($variants as $variant) {
            $end = $cursor->modify("+{$variant->blockingMinutes()} minutes");

            $plan[] = [
                'variant' => $variant,
                'starts_at' => $cursor,
                'ends_at' => $end,
                'price_cents' => $variant->price_cents,
            ];

            $cursor = $end;
        }

        return $plan;
    }

    /**
     * Pessimistic overlap check inside the transaction: locks the staff
     * member's blocking items intersecting the candidate interval.
     */
    private function assertIntervalFree(StaffMember $staff, TimeInterval $interval): void
    {
        $conflicting = AppointmentItem::query()
            ->where('staff_member_id', $staff->id)
            ->where('is_blocking', 1)
            ->where('starts_at', '<', $interval->end)
            ->where('ends_at', '>', $interval->start)
            ->lockForUpdate()
            ->exists();

        if ($conflicting) {
            throw SlotUnavailable::forStaff($staff->uuid);
        }
    }

    private function invalidateAvailability(int $staffMemberId, Location $location, Appointment $appointment): void
    {
        $timezone = new DateTimeZone($location->timezone);

        $this->cacheVersion->bumpMany($appointment->tenant_id, $staffMemberId, [
            $appointment->starts_at->toDateTimeImmutable()->setTimezone($timezone)->format('Y-m-d'),
            $appointment->ends_at->toDateTimeImmutable()->setTimezone($timezone)->format('Y-m-d'),
        ]);
    }
}
