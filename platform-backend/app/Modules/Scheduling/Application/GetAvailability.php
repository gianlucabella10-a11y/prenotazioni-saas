<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Foundation\Http\ApiException;
use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\Catalog\Infrastructure\Models\Location;
use App\Modules\Catalog\Infrastructure\Models\ServiceVariant;
use App\Modules\Scheduling\Domain\AvailabilityCalculator;
use App\Modules\Scheduling\Domain\TimeInterval;
use App\Modules\Scheduling\Infrastructure\Models\AppointmentItem;
use App\Modules\Scheduling\Infrastructure\Models\ScheduleException;
use App\Modules\Scheduling\Infrastructure\Models\StaffSchedule;
use App\Modules\Staff\Infrastructure\Models\StaffMember;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Collection;

/**
 * Availability read model (docs/30 §3): expands recurring rules in the
 * location's timezone, subtracts exceptions and busy items, and produces
 * bookable slot starts per staff member — cached per (staff, day, duration)
 * with versioned keys.
 *
 * The cache is never authoritative: BookAppointment re-verifies inside the
 * locking transaction.
 */
final readonly class GetAvailability
{
    private const SLOTS_TTL_SECONDS = 60;

    public function __construct(
        private CurrentTenant $currentTenant,
        private AvailabilityCalculator $calculator,
        private AvailabilityCacheVersion $cacheVersion,
        private Cache $cache,
    ) {
    }

    /**
     * @param list<string> $variantUuids one or more variants chained in a single visit (docs/30 §6)
     *
     * @return array{slots: array<string, list<array{starts_at: string, staff_uuid: string}>>, duration_minutes: int}
     */
    public function execute(
        string $locationUuid,
        array $variantUuids,
        ?string $staffUuid,
        string $fromDate,
        string $toDate,
    ): array {
        $location = Location::query()->where('uuid', $locationUuid)->firstOrFail();
        $timezone = new DateTimeZone($location->timezone);

        $variants = $this->loadVariants($variantUuids);
        $serviceMinutes = (int) $variants->sum(fn (ServiceVariant $v): int => $v->blockingMinutes());

        $staffCandidates = $this->staffCandidates($variants, $staffUuid);

        if ($staffCandidates->isEmpty()) {
            return ['slots' => [], 'duration_minutes' => $serviceMinutes];
        }

        [$earliest, $latest] = $this->bookingBounds($location);
        $days = $this->localDays($fromDate, $toDate, $location);

        $slotsByDay = [];

        foreach ($days as $day) {
            $daySlots = [];

            foreach ($staffCandidates as $staff) {
                foreach ($this->slotsForStaffDay($location, $staff, $day, $timezone, $serviceMinutes, $earliest, $latest) as $startIso) {
                    $daySlots[] = ['starts_at' => $startIso, 'staff_uuid' => $staff->uuid];
                }
            }

            usort($daySlots, static fn (array $a, array $b): int => $a['starts_at'] <=> $b['starts_at']);

            if ($daySlots !== []) {
                $slotsByDay[$day] = $daySlots;
            }
        }

        return ['slots' => $slotsByDay, 'duration_minutes' => $serviceMinutes];
    }

    /** @return Collection<int, ServiceVariant> ordered as requested */
    private function loadVariants(array $variantUuids): Collection
    {
        $variants = ServiceVariant::query()
            ->with('service')
            ->whereIn('uuid', $variantUuids)
            ->where('is_active', true)
            ->get()
            ->keyBy('uuid');

        if ($variants->count() !== count(array_unique($variantUuids))) {
            throw ApiException::unprocessable('unknown_service_variant', 'One or more selected services are not available.');
        }

        return collect($variantUuids)->map(fn (string $uuid): ServiceVariant => $variants[$uuid])->values();
    }

    /** @param Collection<int, ServiceVariant> $variants */
    private function staffCandidates(Collection $variants, ?string $staffUuid): Collection
    {
        $serviceIds = $variants->pluck('service_id')->unique();

        $query = StaffMember::query()->where('is_bookable', true);

        if ($staffUuid !== null) {
            $query->where('uuid', $staffUuid);
        }

        // The staff member must be enabled for EVERY service in the chain.
        foreach ($serviceIds as $serviceId) {
            $query->whereHas('services', fn ($q) => $q->where('services.id', $serviceId));
        }

        return $query->get();
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} */
    private function bookingBounds(Location $location): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return [
            $now->modify("+{$location->min_notice_minutes} minutes"),
            $now->modify("+{$location->booking_window_days} days"),
        ];
    }

    /** @return list<string> Y-m-d local days, capped to the booking window */
    private function localDays(string $fromDate, string $toDate, Location $location): array
    {
        $from = new DateTimeImmutable($fromDate);
        $to = new DateTimeImmutable($toDate);

        $maxSpanDays = 31; // request hygiene: one month per call

        $days = [];
        $cursor = $from;

        while ($cursor <= $to && count($days) < $maxSpanDays) {
            $days[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        return $days;
    }

    /** @return list<string> ISO-8601 UTC slot starts */
    private function slotsForStaffDay(
        Location $location,
        StaffMember $staff,
        string $localDate,
        DateTimeZone $timezone,
        int $serviceMinutes,
        DateTimeImmutable $earliest,
        DateTimeImmutable $latest,
    ): array {
        $cacheKey = $this->cacheVersion->slotsKey($this->currentTenant->id(), $staff->id, $localDate, $serviceMinutes);

        return $this->cache->remember($cacheKey, self::SLOTS_TTL_SECONDS, function () use (
            $location, $staff, $localDate, $timezone, $serviceMinutes, $earliest, $latest
        ): array {
            $slots = $this->calculator->slotsForDay(
                localDate: $localDate,
                timezone: $timezone,
                workingRules: $this->workingRulesFor($staff, $location, $localDate),
                exceptions: $this->exceptionsFor($staff, $location, $localDate),
                busy: $this->busyIntervalsFor($staff, $localDate, $timezone),
                serviceMinutes: $serviceMinutes,
                granularityMinutes: $location->slot_granularity_minutes,
                earliestStart: $earliest,
                latestStart: $latest,
            );

            return array_map(
                static fn (DateTimeImmutable $s): string => $s->format('Y-m-d\TH:i:s\Z'),
                $slots,
            );
        });
    }

    /**
     * Staff rules already constrained to the location's opening (the wizard
     * creates staff schedules within location hours; the intersection with
     * location schedules guards manual edits).
     *
     * @return list<array{start: string, end: string}>
     */
    private function workingRulesFor(StaffMember $staff, Location $location, string $localDate): array
    {
        $weekday = (int) (new DateTimeImmutable($localDate))->format('N') - 1; // ISO: 0 = Monday

        $staffRules = StaffSchedule::query()
            ->where('staff_member_id', $staff->id)
            ->where('location_id', $location->id)
            ->where('weekday', $weekday)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $localDate))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $localDate))
            ->get(['start_time', 'end_time']);

        return $staffRules
            ->map(static fn ($rule): array => [
                'start' => substr((string) $rule->start_time, 0, 5),
                'end' => substr((string) $rule->end_time, 0, 5),
            ])
            ->all();
    }

    /** @return list<array{start: ?string, end: ?string, kind: string}> */
    private function exceptionsFor(StaffMember $staff, Location $location, string $localDate): array
    {
        return ScheduleException::query()
            ->where('date_start', '<=', $localDate)
            ->where('date_end', '>=', $localDate)
            ->where(function ($q) use ($staff, $location): void {
                $q->where(fn ($lq) => $lq->where('scope', ScheduleException::SCOPE_LOCATION)->where('location_id', $location->id))
                    ->orWhere(fn ($sq) => $sq->where('scope', ScheduleException::SCOPE_STAFF)->where('staff_member_id', $staff->id));
            })
            ->get(['time_start', 'time_end', 'kind'])
            ->map(static fn ($e): array => [
                'start' => $e->time_start === null ? null : substr((string) $e->time_start, 0, 5),
                'end' => $e->time_end === null ? null : substr((string) $e->time_end, 0, 5),
                'kind' => (string) $e->kind,
            ])
            ->all();
    }

    /** @return list<TimeInterval> blocking items overlapping the local day (UTC) */
    private function busyIntervalsFor(StaffMember $staff, string $localDate, DateTimeZone $timezone): array
    {
        $utc = new DateTimeZone('UTC');
        $dayStart = (new DateTimeImmutable("{$localDate} 00:00", $timezone))->setTimezone($utc);
        $dayEnd = (new DateTimeImmutable("{$localDate} 00:00", $timezone))->modify('+1 day')->setTimezone($utc);

        return AppointmentItem::query()
            ->where('staff_member_id', $staff->id)
            ->where('is_blocking', 1)
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get(['starts_at', 'ends_at'])
            ->map(static fn (AppointmentItem $item): TimeInterval => $item->interval())
            ->all();
    }
}
