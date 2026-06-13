<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Infrastructure\Models\ServiceVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceVariant> */
class ServiceVariantFactory extends Factory
{
    protected $model = ServiceVariant::class;

    public function definition(): array
    {
        return [
            'name' => 'Standard',
            'duration_minutes' => 30,
            'buffer_after_minutes' => 0,
            'price_cents' => 1800,
            'currency' => 'EUR',
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
