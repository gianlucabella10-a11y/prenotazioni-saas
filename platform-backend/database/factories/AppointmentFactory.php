<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Infrastructure\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $start = now()->addDays(2)->setTime(10, 0);

        return [
            'status' => AppointmentStatus::Confirmed,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(30),
            'total_price_cents' => 1800,
            'currency' => 'EUR',
            'source' => 'app',
            'confirmed_at' => now(),
        ];
    }

    public function past(): static
    {
        $start = now()->subDays(1)->setTime(10, 0);

        return $this->state(fn (): array => [
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(30),
        ]);
    }
}
