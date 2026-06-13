<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application\Events;

final readonly class AppointmentCancelled
{
    public function __construct(
        public int $appointmentId,
        public int $tenantId,
        public string $cancelledBy, // customer | tenant
    ) {
    }
}
