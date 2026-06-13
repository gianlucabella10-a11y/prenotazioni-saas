<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Pure domain service: computes bookable slot start times for one staff
 * member on one local day.
 *
 * Timezone rule (docs/30 §2): recurring working rules and exceptions are
 * expressed in the LOCAL time of the location and expanded per-day in that
 * zone — DST days of 23/25 hours are correct by construction. Busy
 * intervals and the returned slots are UTC instants.
 *
 * No I/O, no framework: fully unit-testable.
 */
final class AvailabilityCalculator
{
    /**
     * @param string $localDate Y-m-d in the location timezone
     * @param list<array{start: string, end: string}> $workingRules local "HH:MM" open intervals (staff ∩ location, already intersected by the caller)
     * @param list<array{start: ?string, end: ?string, kind: string}> $exceptions local-time exceptions for the day; null start/end = whole day
     * @param list<TimeInterval> $busy existing blocking items (UTC), buffers included
     * @param int $serviceMinutes total chained duration to fit, buffers included
     * @param int $granularityMinutes slot grid step
     * @param DateTimeImmutable $earliestStart UTC lower bound (now + minimum notice)
     * @param DateTimeImmutable $latestStart UTC upper bound (booking window end)
     *
     * @return list<DateTimeImmutable> bookable slot starts, UTC, ascending
     */
    public function slotsForDay(
        string $localDate,
        DateTimeZone $timezone,
        array $workingRules,
        array $exceptions,
        array $busy,
        int $serviceMinutes,
        int $granularityMinutes,
        DateTimeImmutable $earliestStart,
        DateTimeImmutable $latestStart,
    ): array {
        $open = $this->expandRules($localDate, $timezone, $workingRules);

        foreach ($exceptions as $exception) {
            $interval = $this->expandException($localDate, $timezone, $exception);

            if ($interval === null) {
                continue;
            }

            $open = $exception['kind'] === 'open_extra'
                ? $this->mergeInterval($open, $interval)
                : TimeInterval::subtractAll($open, [$interval]);
        }

        $free = TimeInterval::subtractAll($open, $busy);

        return $this->slotStarts($free, $serviceMinutes, $granularityMinutes, $earliestStart, $latestStart);
    }

    /**
     * Expand local "HH:MM" rules into UTC intervals for the given local day.
     *
     * @param list<array{start: string, end: string}> $rules
     *
     * @return list<TimeInterval>
     */
    private function expandRules(string $localDate, DateTimeZone $timezone, array $rules): array
    {
        $intervals = [];

        foreach ($rules as $rule) {
            $interval = $this->localInterval($localDate, $timezone, $rule['start'], $rule['end']);

            if ($interval !== null) {
                $intervals[] = $interval;
            }
        }

        return $intervals;
    }

    /** @param array{start: ?string, end: ?string, kind: string} $exception */
    private function expandException(string $localDate, DateTimeZone $timezone, array $exception): ?TimeInterval
    {
        $start = $exception['start'] ?? '00:00';
        $end = $exception['end'] ?? '24:00';

        return $this->localInterval($localDate, $timezone, $start, $end);
    }

    /**
     * Build a UTC interval from local wall-clock times, handling DST: a
     * non-existent local time (spring-forward gap) is normalized forward by
     * PHP; an interval collapsing to zero or negative is dropped.
     */
    private function localInterval(string $localDate, DateTimeZone $timezone, string $startTime, string $endTime): ?TimeInterval
    {
        $utc = new DateTimeZone('UTC');

        $start = new DateTimeImmutable("{$localDate} {$startTime}", $timezone);

        $end = $endTime === '24:00'
            ? (new DateTimeImmutable("{$localDate} 00:00", $timezone))->modify('+1 day')
            : new DateTimeImmutable("{$localDate} {$endTime}", $timezone);

        $startUtc = $start->setTimezone($utc);
        $endUtc = $end->setTimezone($utc);

        return $endUtc > $startUtc ? new TimeInterval($startUtc, $endUtc) : null;
    }

    /**
     * @param list<TimeInterval> $intervals
     *
     * @return list<TimeInterval>
     */
    private function mergeInterval(array $intervals, TimeInterval $extra): array
    {
        $intervals[] = $extra;

        usort($intervals, fn (TimeInterval $a, TimeInterval $b): int => $a->start <=> $b->start);

        $merged = [];

        foreach ($intervals as $interval) {
            $last = end($merged);

            if ($last !== false && $interval->start <= $last->end) {
                if ($interval->end > $last->end) {
                    $merged[array_key_last($merged)] = new TimeInterval($last->start, $interval->end);
                }

                continue;
            }

            $merged[] = $interval;
        }

        return $merged;
    }

    /**
     * Walk each free interval on the granularity grid and keep starts where
     * the full service duration fits within bounds.
     *
     * @param list<TimeInterval> $free
     *
     * @return list<DateTimeImmutable>
     */
    private function slotStarts(
        array $free,
        int $serviceMinutes,
        int $granularityMinutes,
        DateTimeImmutable $earliestStart,
        DateTimeImmutable $latestStart,
    ): array {
        $slots = [];

        usort($free, fn (TimeInterval $a, TimeInterval $b): int => $a->start <=> $b->start);

        foreach ($free as $interval) {
            $cursor = $this->alignToGrid($interval->start, $granularityMinutes);

            while (true) {
                $slotEnd = $cursor->modify("+{$serviceMinutes} minutes");

                if ($slotEnd > $interval->end || $cursor > $latestStart) {
                    break;
                }

                if ($cursor >= $earliestStart) {
                    $slots[] = $cursor;
                }

                $cursor = $cursor->modify("+{$granularityMinutes} minutes");
            }
        }

        return $slots;
    }

    /** Round up to the next grid point (grid anchored to the top of the hour). */
    private function alignToGrid(DateTimeImmutable $instant, int $granularityMinutes): DateTimeImmutable
    {
        $seconds = $granularityMinutes * 60;
        $timestamp = $instant->getTimestamp();
        $aligned = intdiv($timestamp + $seconds - 1, $seconds) * $seconds;

        return $instant->setTimestamp($aligned);
    }
}
