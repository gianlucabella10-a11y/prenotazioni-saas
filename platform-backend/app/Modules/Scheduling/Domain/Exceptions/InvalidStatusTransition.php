<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain\Exceptions;

use App\Modules\Scheduling\Domain\AppointmentStatus;
use DomainException;

final class InvalidStatusTransition extends DomainException
{
    public static function between(AppointmentStatus $from, AppointmentStatus $to): self
    {
        return new self("Appointment cannot transition from '{$from->value}' to '{$to->value}'.");
    }
}
