<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application\Events;

/**
 * Published after a successful booking commit. Carries ids only: listeners
 * rehydrate state (and the tenant context) themselves — safe to queue.
 */
final readonly class AppointmentBooked
{
    public function __construct(
        public int $appointmentId,
        public int $tenantId,
    ) {
    }
}
