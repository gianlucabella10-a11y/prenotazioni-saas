<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Scheduling\Domain\AppointmentStatus;
use PHPUnit\Framework\TestCase;

final class AppointmentStatusTest extends TestCase
{
    public function test_requested_can_be_confirmed_or_cancelled(): void
    {
        $requested = AppointmentStatus::Requested;

        self::assertTrue($requested->canTransitionTo(AppointmentStatus::Confirmed));
        self::assertTrue($requested->canTransitionTo(AppointmentStatus::CancelledByTenant));
        self::assertTrue($requested->canTransitionTo(AppointmentStatus::CancelledByCustomer));
        self::assertFalse($requested->canTransitionTo(AppointmentStatus::Completed));
        self::assertFalse($requested->canTransitionTo(AppointmentStatus::NoShow));
    }

    public function test_confirmed_lifecycle(): void
    {
        $confirmed = AppointmentStatus::Confirmed;

        self::assertTrue($confirmed->canTransitionTo(AppointmentStatus::Completed));
        self::assertTrue($confirmed->canTransitionTo(AppointmentStatus::NoShow));
        self::assertTrue($confirmed->canTransitionTo(AppointmentStatus::CancelledByCustomer));
        self::assertFalse($confirmed->canTransitionTo(AppointmentStatus::Requested));
    }

    public function test_no_show_correction_path(): void
    {
        self::assertTrue(AppointmentStatus::NoShow->canTransitionTo(AppointmentStatus::Completed));
        self::assertFalse(AppointmentStatus::NoShow->canTransitionTo(AppointmentStatus::Confirmed));
    }

    public function test_terminal_states_have_no_exits(): void
    {
        foreach ([AppointmentStatus::Completed, AppointmentStatus::CancelledByCustomer, AppointmentStatus::CancelledByTenant] as $terminal) {
            foreach (AppointmentStatus::cases() as $target) {
                self::assertFalse($terminal->canTransitionTo($target), "{$terminal->value} must not reach {$target->value}");
            }
        }
    }

    public function test_only_requested_and_confirmed_block_agenda(): void
    {
        foreach (AppointmentStatus::cases() as $status) {
            $expected = in_array($status, [AppointmentStatus::Requested, AppointmentStatus::Confirmed], true);

            self::assertSame($expected, $status->blocksAgenda(), $status->value);
        }
    }
}
