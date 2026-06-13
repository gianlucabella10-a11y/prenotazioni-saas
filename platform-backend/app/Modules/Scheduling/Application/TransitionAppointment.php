<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Foundation\Http\ApiException;
use App\Models\User;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Infrastructure\Models\Appointment;
use Illuminate\Database\Connection;

/**
 * Staff-side lifecycle actions (docs/30 §5): confirm a pending request,
 * complete a served appointment, mark a no-show (RF-52, with the customer's
 * denormalized counter), correct a wrong no-show within 7 days.
 */
final readonly class TransitionAppointment
{
    private const NO_SHOW_CORRECTION_DAYS = 7;

    public function __construct(private Connection $db)
    {
    }

    public function confirm(Appointment $appointment, User $actor): Appointment
    {
        $this->db->transaction(function () use ($appointment, $actor): void {
            $appointment->transitionTo(AppointmentStatus::Confirmed, 'staff', $actor->id);
        });

        return $appointment->refresh();
    }

    public function complete(Appointment $appointment, User $actor): Appointment
    {
        $this->db->transaction(function () use ($appointment, $actor): void {
            if ($appointment->status === AppointmentStatus::NoShow) {
                $this->assertWithinCorrectionWindow($appointment);
                $appointment->customer()->firstOrFail()->decrement('no_show_count');
            }

            $appointment->transitionTo(AppointmentStatus::Completed, 'staff', $actor->id);

            $appointment->customer()->firstOrFail()->update(['last_appointment_at' => $appointment->starts_at]);
        });

        return $appointment->refresh();
    }

    public function markNoShow(Appointment $appointment, User $actor): Appointment
    {
        if ($appointment->starts_at->isFuture()) {
            throw ApiException::unprocessable('appointment_not_started', 'A no-show can be recorded only after the appointment time.');
        }

        $this->db->transaction(function () use ($appointment, $actor): void {
            $appointment->transitionTo(AppointmentStatus::NoShow, 'staff', $actor->id);
            $appointment->customer()->firstOrFail()->increment('no_show_count');
        });

        return $appointment->refresh();
    }

    private function assertWithinCorrectionWindow(Appointment $appointment): void
    {
        $markedAt = $appointment->events()
            ->where('to_status', AppointmentStatus::NoShow->value)
            ->latest('id')
            ->value('created_at');

        if ($markedAt !== null && \Illuminate\Support\Carbon::parse($markedAt)->addDays(self::NO_SHOW_CORRECTION_DAYS)->isPast()) {
            throw ApiException::unprocessable('correction_window_passed', 'The no-show correction window has passed.');
        }
    }
}
