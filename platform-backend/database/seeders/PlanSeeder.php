<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\TenantManagement\Infrastructure\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Production seed: the commercial plans (docs/15, docs/10). Idempotent via
 * updateOrCreate so it can run on every deploy.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'base',
                'name' => 'Base',
                'price_monthly_cents' => 25000,
                'currency' => 'EUR',
                'features' => [
                    'waitlist' => false,
                    'campaigns' => false,
                    'multi_location' => false,
                    'clinical_notes' => false,
                ],
                'quotas' => ['max_staff' => 5, 'max_locations' => 1, 'max_services' => 50, 'max_broadcasts_month' => 0],
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'price_monthly_cents' => 25000,
                'currency' => 'EUR',
                'features' => [
                    'waitlist' => true,
                    'campaigns' => true,
                    'multi_location' => true,
                    'clinical_notes' => true,
                ],
                'quotas' => ['max_staff' => 15, 'max_locations' => 3, 'max_services' => 200, 'max_broadcasts_month' => 8],
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'price_monthly_cents' => 0, // negotiated per contract
                'currency' => 'EUR',
                'features' => [
                    'waitlist' => true,
                    'campaigns' => true,
                    'multi_location' => true,
                    'clinical_notes' => true,
                ],
                'quotas' => ['max_staff' => null, 'max_locations' => null, 'max_services' => null, 'max_broadcasts_month' => 30],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
