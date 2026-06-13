<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TenantManagement\Domain\TenantStatus;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'legal_name' => $name . ' S.r.l.',
            'display_name' => $name,
            'sector' => 'barber',
            'status' => TenantStatus::Active,
            'default_timezone' => 'Europe/Rome',
            'default_locale' => 'it',
            'api_key' => Str::random(40),
            'settings' => config('booking.default_settings'),
            'health_data_enabled' => false,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => TenantStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }

    public function approvalMode(): static
    {
        return $this->state(fn (array $attributes): array => [
            'settings' => array_merge(
                $attributes['settings'] ?? [],
                ['booking_confirmation_mode' => 'request_approve'],
            ),
        ]);
    }
}
