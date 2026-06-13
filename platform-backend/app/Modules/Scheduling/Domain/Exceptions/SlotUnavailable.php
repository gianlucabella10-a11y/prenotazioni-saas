<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain\Exceptions;

use DomainException;

/**
 * The requested interval is no longer free (lost the race or stale slot
 * list). Rendered as HTTP 409 `slot_unavailable` (docs/25 §3).
 */
final class SlotUnavailable extends DomainException
{
    public static function forStaff(string $staffUuid): self
    {
        return new self("The selected time slot is no longer available for staff {$staffUuid}.");
    }
}
