<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Half-open interval [start, end) on the UTC timeline.
 *
 * Half-open semantics make back-to-back bookings natural: an appointment
 * ending at 10:00 does not overlap one starting at 10:00.
 */
final readonly class TimeInterval
{
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
        if ($end <= $start) {
            throw new InvalidArgumentException('Interval end must be after start.');
        }
    }

    public static function fromStartAndMinutes(DateTimeImmutable $start, int $minutes): self
    {
        return new self($start, $start->modify("+{$minutes} minutes"));
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }

    public function contains(self $other): bool
    {
        return $this->start <= $other->start && $other->end <= $this->end;
    }

    public function durationMinutes(): int
    {
        return intdiv($this->end->getTimestamp() - $this->start->getTimestamp(), 60);
    }

    /**
     * Subtract another interval, returning the 0, 1 or 2 remaining pieces.
     *
     * @return list<self>
     */
    public function subtract(self $other): array
    {
        if (! $this->overlaps($other)) {
            return [$this];
        }

        $pieces = [];

        if ($this->start < $other->start) {
            $pieces[] = new self($this->start, $other->start);
        }

        if ($other->end < $this->end) {
            $pieces[] = new self($other->end, $this->end);
        }

        return $pieces;
    }

    /**
     * Subtract many intervals from many, keeping the free remainder.
     *
     * @param list<self> $base
     * @param list<self> $toRemove
     *
     * @return list<self>
     */
    public static function subtractAll(array $base, array $toRemove): array
    {
        foreach ($toRemove as $removal) {
            $next = [];

            foreach ($base as $interval) {
                foreach ($interval->subtract($removal) as $piece) {
                    $next[] = $piece;
                }
            }

            $base = $next;
        }

        return $base;
    }
}
