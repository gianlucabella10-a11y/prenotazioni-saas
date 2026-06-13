<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Scheduling\Domain\AvailabilityCalculator;
use App\Modules\Scheduling\Domain\TimeInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class AvailabilityCalculatorTest extends TestCase
{
    private AvailabilityCalculator $calculator;
    private DateTimeZone $rome;
    private DateTimeImmutable $farPast;
    private DateTimeImmutable $farFuture;

    protected function setUp(): void
    {
        $this->calculator = new AvailabilityCalculator();
        $this->rome = new DateTimeZone('Europe/Rome');
        $this->farPast = new DateTimeImmutable('2020-01-01', new DateTimeZone('UTC'));
        $this->farFuture = new DateTimeImmutable('2030-01-01', new DateTimeZone('UTC'));
    }

    /** @return list<string> local HH:i slot starts for readability */
    private function localSlots(array $slots): array
    {
        return array_map(
            fn (DateTimeImmutable $s): string => $s->setTimezone($this->rome)->format('H:i'),
            $slots,
        );
    }

    public function test_plain_working_day_produces_grid_slots(): void
    {
        $slots = $this->calculator->slotsForDay(
            localDate: '2026-06-15',
            timezone: $this->rome,
            workingRules: [['start' => '09:00', 'end' => '11:00']],
            exceptions: [],
            busy: [],
            serviceMinutes: 30,
            granularityMinutes: 30,
            earliestStart: $this->farPast,
            latestStart: $this->farFuture,
        );

        self::assertSame(['09:00', '09:30', '10:00', '10:30'], $this->localSlots($slots));
    }

    public function test_busy_interval_removes_conflicting_slots(): void
    {
        // 09:30-10:00 booked (UTC: Rome is +2 in June).
        $busy = new TimeInterval(
            new DateTimeImmutable('2026-06-15 07:30', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-15 08:00', new DateTimeZone('UTC')),
        );

        $slots = $this->calculator->slotsForDay(
            '2026-06-15',
            $this->rome,
            [['start' => '09:00', 'end' => '11:00']],
            [],
            [$busy],
            30,
            30,
            $this->farPast,
            $this->farFuture,
        );

        self::assertSame(['09:00', '10:00', '10:30'], $this->localSlots($slots));
    }

    public function test_whole_day_closure_exception_empties_the_day(): void
    {
        $slots = $this->calculator->slotsForDay(
            '2026-06-15',
            $this->rome,
            [['start' => '09:00', 'end' => '19:00']],
            [['start' => null, 'end' => null, 'kind' => 'closed']],
            [],
            30,
            15,
            $this->farPast,
            $this->farFuture,
        );

        self::assertSame([], $slots);
    }

    public function test_partial_closure_carves_out_lunch_break(): void
    {
        $slots = $this->calculator->slotsForDay(
            '2026-06-15',
            $this->rome,
            [['start' => '09:00', 'end' => '15:00']],
            [['start' => '12:00', 'end' => '14:00', 'kind' => 'closed']],
            [],
            60,
            60,
            $this->farPast,
            $this->farFuture,
        );

        self::assertSame(['09:00', '10:00', '11:00', '14:00'], $this->localSlots($slots));
    }

    public function test_open_extra_exception_adds_evening_slots(): void
    {
        $slots = $this->calculator->slotsForDay(
            '2026-06-15',
            $this->rome,
            [['start' => '09:00', 'end' => '10:00']],
            [['start' => '20:00', 'end' => '21:00', 'kind' => 'open_extra']],
            [],
            30,
            30,
            $this->farPast,
            $this->farFuture,
        );

        self::assertSame(['09:00', '09:30', '20:00', '20:30'], $this->localSlots($slots));
    }

    public function test_service_longer_than_remaining_window_is_excluded(): void
    {
        $slots = $this->calculator->slotsForDay(
            '2026-06-15',
            $this->rome,
            [['start' => '09:00', 'end' => '10:00']],
            [],
            [],
            45,
            15,
            $this->farPast,
            $this->farFuture,
        );

        // 45' must END within 10:00: only 09:00 and 09:15 fit.
        self::assertSame(['09:00', '09:15'], $this->localSlots($slots));
    }

    public function test_dst_spring_forward_day_is_23_hours_and_correct(): void
    {
        // Europe/Rome, 2026-03-29: clocks jump 02:00 -> 03:00.
        $slots = $this->calculator->slotsForDay(
            '2026-03-29',
            $this->rome,
            [['start' => '09:00', 'end' => '11:00']],
            [],
            [],
            60,
            60,
            $this->farPast,
            $this->farFuture,
        );

        self::assertSame(['09:00', '10:00'], $this->localSlots($slots));

        // 09:00 local on the DST day is 07:00 UTC (already +2 after the jump).
        self::assertSame('07:00', $slots[0]->format('H:i'));
    }

    public function test_dst_fall_back_day_is_25_hours_and_correct(): void
    {
        // Europe/Rome, 2026-10-25: clocks fall back 03:00 -> 02:00.
        $slots = $this->calculator->slotsForDay(
            '2026-10-25',
            $this->rome,
            [['start' => '09:00', 'end' => '11:00']],
            [],
            [],
            60,
            60,
            $this->farPast,
            $this->farFuture,
        );

        self::assertSame(['09:00', '10:00'], $this->localSlots($slots));

        // After fall-back Rome is +1: 09:00 local = 08:00 UTC.
        self::assertSame('08:00', $slots[0]->format('H:i'));
    }

    public function test_earliest_bound_excludes_past_and_too_soon_slots(): void
    {
        $earliest = new DateTimeImmutable('2026-06-15 08:00', new DateTimeZone('UTC')); // 10:00 Rome

        $slots = $this->calculator->slotsForDay(
            '2026-06-15',
            $this->rome,
            [['start' => '09:00', 'end' => '12:00']],
            [],
            [],
            60,
            60,
            $earliest,
            $this->farFuture,
        );

        self::assertSame(['10:00', '11:00'], $this->localSlots($slots));
    }

    public function test_no_rules_means_no_slots(): void
    {
        $slots = $this->calculator->slotsForDay(
            '2026-06-15',
            $this->rome,
            [],
            [],
            [],
            30,
            15,
            $this->farPast,
            $this->farFuture,
        );

        self::assertSame([], $slots);
    }
}
