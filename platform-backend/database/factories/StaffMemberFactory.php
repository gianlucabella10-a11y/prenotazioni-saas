<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Staff\Infrastructure\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffMember> */
class StaffMemberFactory extends Factory
{
    protected $model = StaffMember::class;

    public function definition(): array
    {
        return [
            'display_name' => fake()->firstName(),
            'is_bookable' => true,
            'sort_order' => 0,
        ];
    }
}
