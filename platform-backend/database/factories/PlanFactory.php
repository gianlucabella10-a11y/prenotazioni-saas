<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TenantManagement\Infrastructure\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'code' => 'base-' . fake()->unique()->numberBetween(1, 100000),
            'name' => 'Base',
            'price_monthly_cents' => 25000,
            'currency' => 'EUR',
            'features' => ['waitlist' => false, 'campaigns' => false],
            'quotas' => ['max_staff' => 5, 'max_locations' => 1, 'max_services' => 50],
            'is_active' => true,
        ];
    }

    public function pro(): static
    {
        return $this->state(fn (): array => [
            'code' => 'pro-' . fake()->unique()->numberBetween(1, 100000),
            'name' => 'Pro',
            'features' => ['waitlist' => true, 'campaigns' => true],
            'quotas' => ['max_staff' => 15, 'max_locations' => 3, 'max_services' => 200],
        ]);
    }
}
