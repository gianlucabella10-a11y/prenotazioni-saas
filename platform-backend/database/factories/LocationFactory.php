<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Infrastructure\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'address' => fake()->streetAddress(),
            'timezone' => 'Europe/Rome',
            'status' => 'active',
            'booking_window_days' => 60,
            'cancellation_cutoff_minutes' => 1440,
            'min_notice_minutes' => 60,
            'slot_granularity_minutes' => 15,
        ];
    }
}
