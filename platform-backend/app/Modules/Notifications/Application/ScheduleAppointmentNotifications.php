<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\Notifications\Infrastructure\Models\NotificationRecord;
use App\Modules\Scheduling\Application\Events\AppointmentBooked;
use App\Modules\Scheduling\Application\Events\AppointmentCancelled;
use App\Modules\Scheduling\Infrastructure\Models\Appointment;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Reacts to booking lifecycle events by writing outbox rows (docs/29 §3):
 * an immediate confirmation plus one reminder per tenant-configured offset.
 * Jobs within the near horizon are dispatched directly; the rest are
 * promoted by the hourly sweep (hybrid DB-scheduled + delayed, docs/33 #50).
 */
final readonly class ScheduleAppointmentNotifications
{
    private const DIRECT_DISPATCH_HORIZON_HOURS = 48;

    public function __construct(private CurrentTenant $currentTenant)
    {
    }

    public function onBooked(AppointmentBooked $event): void
    {
        $appointment = Appointment::query()->with(['customer', 'location', 'items'])->find($event->appointmentId);

        if ($appointment === null) {
            return;
        }

        $confirmation = $this->createRecord(
            $appointment,
            $this->currentTenant->get()->requiresBookingApproval() ? 'booking_requested' : 'booking_confirmed',
            now()->toDateTimeImmutable(),
        );

        $this->dispatchIfNear($confirmation);

        foreach ($this->currentTenant->get()->reminderOffsetsHours() as $offsetHours) {
            $remindAt = $appointment->starts_at->toDateTimeImmutable()->modify("-{$offsetHours} hours");

            if ($remindAt <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
                continue; // appointment closer than the offset
            }

            $this->dispatchIfNear(
                $this->createRecord($appointment, 'booking_reminder', $remindAt)
            );
        }
    }

    public function onCancelled(AppointmentCancelled $event): void
    {
        $appointment = Appointment::query()->with(['customer', 'location', 'items'])->find($event->appointmentId);

        if ($appointment === null) {
            return;
        }

        // Reminders for a cancelled appointment are obsolete.
        NotificationRecord::query()
            ->where('appointment_id', $appointment->id)
            ->where('template_code', 'booking_reminder')
            ->where('status', NotificationRecord::STATUS_SCHEDULED)
            ->update(['status' => NotificationRecord::STATUS_OBSOLETE]);

        if ($event->cancelledBy === 'tenant') {
            $this->dispatchIfNear(
                $this->createRecord($appointment, 'booking_cancelled_by_tenant', now()->toDateTimeImmutable())
            );
        }
    }

    private function createRecord(Appointment $appointment, string $templateCode, DateTimeImmutable $when): NotificationRecord
    {
        $location = $appointment->location;
        $localTime = $appointment->starts_at
            ->toDateTimeImmutable()
            ->setTimezone(new DateTimeZone($location->timezone))
            ->format('d/m/Y H:i');

        $appName = BrandProfile::query()->value('app_name') ?? '';

        return NotificationRecord::query()->create([
            'customer_id' => $appointment->customer_id,
            'channel' => NotificationRecord::CHANNEL_PUSH,
            'template_code' => $templateCode,
            'payload' => [
                'appointment_uuid' => $appointment->uuid,
                'app_name' => $appName,
                'customer_name' => $appointment->customer->first_name,
                'service_name' => $appointment->items->pluck('service_name_snapshot')->implode(' + '),
                'local_time' => $localTime,
                'location_name' => $location->name,
                'locale' => $appointment->customer->user?->locale ?? $this->currentTenant->get()->locale,
            ],
            'appointment_id' => $appointment->id,
            'status' => NotificationRecord::STATUS_SCHEDULED,
            'scheduled_for' => $when,
        ]);
    }

    private function dispatchIfNear(NotificationRecord $record): void
    {
        $horizon = now()->addHours(self::DIRECT_DISPATCH_HORIZON_HOURS);

        if ($record->scheduled_for->lte($horizon)) {
            SendNotificationJob::dispatch($record->id, $record->tenant_id)
                ->delay($record->scheduled_for);
        }
    }
}
