<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\AppFactory\Domain\AppProjectStatus;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AppProject> */
class AppProjectFactory extends Factory
{
    protected $model = AppProject::class;

    public function definition(): array
    {
        $shortcode = Str::lower(Str::random(8));

        return [
            'tenant_id' => Tenant::factory(),
            'slug' => 'app-'.$shortcode,
            'shortcode' => $shortcode,
            'store_name' => $this->faker->company(),
            'bundle_id' => "com.platform.t{$shortcode}",
            'package_name' => "com.platform.t{$shortcode}",
            'template_code' => 'default',
            'font_style' => null,
            'powered_by_enabled' => true,
            'build_status' => AppProjectStatus::Draft,
        ];
    }
}
