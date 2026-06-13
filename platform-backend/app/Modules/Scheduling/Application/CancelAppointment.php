<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Foundation\Http\ApiException;
use App\Modules\Scheduling\Application\Events\AppointmentCancelled;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Infrastructure\Models\Appointment;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;

/**
 * Customer- or tenant-initiated cancellation (docs/08 Flusso 4). The
 * customer path enforces the location's cancellation cutoff; the tenant
 * path is unrestricted but always notifies.
 */
final readonly class CancelAppointment
{
    public function __construct(
        private Connection $db,
        private Dispatcher $events,
        private AvailabilityCacheVersion $cacheVersion,
    ) {
    }

    public function byCustomer(Appointment $appointment, ?string $reason = null): Appointment
    {
        $cutoffMinutes = (int) $appointment->location()->firstOrFail()->cancellation_cutoff_minutes;
        $cutoff = $appointment->starts_at->toDateTimeImmutable()->modify("-{$cutoffMinutes} minutes");

        if (new DateTimeImmutable('now', new DateTimeZone('UTC')) > $cutoff) {
            throw ApiException::unprocessable(
                'cutoff_passed',
                'The cancellation window has passed. Please contact the business directly.'
            );
        }

        return $this->cancel($appointment, AppointmentStatus::CancelledByCustomer, 'customer', $reason);
    }

    public function byTenant(Appointment $appointment, int $actorUserId, ?string $reason = null): Appointment
    {
        return $this->cancel($appointment, AppointmentStatus::CancelledByTenant, 'staff', $reason, $actorUserId);
    }

    private function cancel(
        Appointment $appointment,
        AppointmentStatus $target,
        string $actorType,
        ?string $reason,
        ?int $actorId = null,
    ): Appointment {
        $this->db->transaction(function () use ($appointment, $target, $actorType, $reason, $actorId): void {
            $appointment->transitionTo($target, $actorType, $actorId, $reason);
        });

        $this->invalidateAvailability($appointment);

        $this->events->dispatch(new AppointmentCancelled(
            $appointment->id,
            $appointment->tenant_id,
            $actorType === 'customer' ? 'customer' : 'tenant',
        ));

        return $appointment->refresh();
    }

    private function invalidateAvailability(Appointment $appointment): void
    {
        $location = $appointment->location()->firstOrFail();
        $timezone = new DateTimeZone($location->timezone);

        foreach ($appointment->items as $item) {
            $this->cacheVersion->bump(
                $appointment->tenant_id,
                $item->staff_member_id,
                $item->starts_at->toDateTimeImmutable()->setTimezone($timezone)->format('Y-m-d'),
            );
        }
    }
}
