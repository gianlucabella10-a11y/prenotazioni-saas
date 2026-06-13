<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandProfile> */
class BrandProfileFactory extends Factory
{
    protected $model = BrandProfile::class;

    public function definition(): array
    {
        $theme = config('branding.default_theme');

        return [
            'app_name' => fake()->company(),
            'primary_color' => $theme['colors']['primary'],
            'secondary_color' => $theme['colors']['secondary'],
            'theme' => $theme,
            'config_version' => 1,
            'contrast_validated' => true,
        ];
    }
}
