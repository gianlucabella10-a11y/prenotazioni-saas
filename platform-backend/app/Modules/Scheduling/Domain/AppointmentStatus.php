<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

/**
 * Appointment state machine (docs/30 §5). Transitions are the single source
 * of truth: every status change must pass canTransitionTo.
 */
enum AppointmentStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case CancelledByCustomer = 'cancelled_by_customer';
    case CancelledByTenant = 'cancelled_by_tenant';
    case NoShow = 'no_show';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, match ($this) {
            self::Requested => [self::Confirmed, self::CancelledByTenant, self::CancelledByCustomer],
            self::Confirmed => [self::Completed, self::CancelledByCustomer, self::CancelledByTenant, self::NoShow],
            self::NoShow => [self::Completed], // 7-day correction window, enforced in the use case
            self::Completed, self::CancelledByCustomer, self::CancelledByTenant => [],
        }, true);
    }

    /** Whether items in this status block the staff agenda. */
    public function blocksAgenda(): bool
    {
        return $this === self::Requested || $this === self::Confirmed;
    }

    public function isCancelled(): bool
    {
        return $this === self::CancelledByCustomer || $this === self::CancelledByTenant;
    }
}
