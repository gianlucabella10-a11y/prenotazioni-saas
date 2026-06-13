<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Infrastructure\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'name' => 'Taglio capelli',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
