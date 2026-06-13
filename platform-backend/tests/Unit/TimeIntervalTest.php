<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Scheduling\Domain\TimeInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TimeIntervalTest extends TestCase
{
    private function interval(string $start, string $end): TimeInterval
    {
        return new TimeInterval(new DateTimeImmutable($start), new DateTimeImmutable($end));
    }

    public function test_rejects_inverted_bounds(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->interval('2026-06-12 11:00', '2026-06-12 10:00');
    }

    public function test_half_open_semantics_allow_back_to_back(): void
    {
        $first = $this->interval('2026-06-12 09:00', '2026-06-12 10:00');
        $second = $this->interval('2026-06-12 10:00', '2026-06-12 11:00');

        self::assertFalse($first->overlaps($second));
        self::assertFalse($second->overlaps($first));
    }

    public function test_detects_partial_overlap(): void
    {
        $a = $this->interval('2026-06-12 09:00', '2026-06-12 10:00');
        $b = $this->interval('2026-06-12 09:30', '2026-06-12 10:30');

        self::assertTrue($a->overlaps($b));
        self::assertTrue($b->overlaps($a));
    }

    public function test_subtract_middle_produces_two_pieces(): void
    {
        $base = $this->interval('2026-06-12 09:00', '2026-06-12 12:00');
        $hole = $this->interval('2026-06-12 10:00', '2026-06-12 11:00');

        $pieces = $base->subtract($hole);

        self::assertCount(2, $pieces);
        self::assertSame('09:00', $pieces[0]->start->format('H:i'));
        self::assertSame('10:00', $pieces[0]->end->format('H:i'));
        self::assertSame('11:00', $pieces[1]->start->format('H:i'));
        self::assertSame('12:00', $pieces[1]->end->format('H:i'));
    }

    public function test_subtract_covering_interval_leaves_nothing(): void
    {
        $base = $this->interval('2026-06-12 09:00', '2026-06-12 10:00');
        $cover = $this->interval('2026-06-12 08:00', '2026-06-12 11:00');

        self::assertSame([], $base->subtract($cover));
    }

    public function test_subtract_all_against_multiple_busy_blocks(): void
    {
        $free = TimeInterval::subtractAll(
            [$this->interval('2026-06-12 09:00', '2026-06-12 13:00')],
            [
                $this->interval('2026-06-12 09:30', '2026-06-12 10:00'),
                $this->interval('2026-06-12 11:00', '2026-06-12 12:00'),
            ],
        );

        self::assertCount(3, $free);
        self::assertSame(30, $free[0]->durationMinutes());
        self::assertSame(60, $free[1]->durationMinutes());
        self::assertSame(60, $free[2]->durationMinutes());
    }
}
