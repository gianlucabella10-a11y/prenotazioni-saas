<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Enums\UserType;
use App\Foundation\Http\ApiException;
use App\Foundation\Tenancy\CurrentTenant;
use App\Models\User;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\Catalog\Infrastructure\Models\Location;
use App\Modules\Catalog\Infrastructure\Models\Service;
use App\Modules\Catalog\Infrastructure\Models\ServiceCategory;
use App\Modules\TenantManagement\Domain\TenantStatus;
use App\Modules\TenantManagement\Infrastructure\Models\Plan;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;

/**
 * Automated tenant provisioning (docs/08 Flusso 1, docs/28 §5): one
 * transaction creates the tenant, its subscription, a default brand
 * profile, the first location with standard opening hours and the
 * sector-specific starter catalog. No DDL: provisioning takes seconds.
 *
 * Runs platform-level (bypass) — only reachable by super admins.
 */
final readonly class ProvisionTenant
{
    public function __construct(
        private Connection $db,
        private CurrentTenant $currentTenant,
        private Config $config,
        private AuditLogger $audit,
    ) {
    }

    /**
     * @param array{
     *     legal_name: string, display_name: string, sector: string,
     *     timezone: string, locale: string, plan_code: string,
     *     app_name: string, admin_email: string, health_data?: bool
     * } $input
     *
     * @return array{tenant: Tenant, admin: User, invite_token: string}
     */
    public function execute(array $input, int $actorUserId): array
    {
        $plan = Plan::query()->where('code', $input['plan_code'])->where('is_active', true)->first();

        if ($plan === null) {
            throw ApiException::unprocessable('unknown_plan', 'The requested plan does not exist.');
        }

        $sector = $input['sector'];

        if (in_array($sector, ['dental', 'medical', 'physio'], true) && ! ($input['health_data'] ?? false)) {
            // Health-sector tenants must explicitly activate the module
            // (docs/17 criticità 3): refuse silent omissions.
            throw ApiException::unprocessable(
                'health_module_required',
                'Tenants in healthcare sectors must activate the health data module.'
            );
        }

        $result = $this->currentTenant->bypass(function () use ($input, $plan, $sector): array {
            return $this->db->transaction(function () use ($input, $plan, $sector): array {
                $tenant = Tenant::query()->create([
                    'legal_name' => $input['legal_name'],
                    'display_name' => $input['display_name'],
                    'sector' => $sector,
                    'status' => TenantStatus::Onboarding,
                    'default_timezone' => $input['timezone'],
                    'default_locale' => $input['locale'],
                    'api_key' => Str::random(40),
                    'onboarding_state' => ['wizard_step' => 1, 'completed' => []],
                    'settings' => $this->config->get('booking.default_settings'),
                    'health_data_enabled' => (bool) ($input['health_data'] ?? false),
                ]);

                $tenant->subscriptions()->create([
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'current_period_end' => now()->addMonth(),
                ]);

                BrandProfile::query()->create([
                    'tenant_id' => $tenant->id,
                    'app_name' => $input['app_name'],
                    'primary_color' => $this->config->get('branding.default_theme.colors.primary'),
                    'secondary_color' => $this->config->get('branding.default_theme.colors.secondary'),
                    'theme' => $this->config->get('branding.default_theme'),
                    'contrast_validated' => true, // curated default palette
                ]);

                $location = $this->createDefaultLocation($tenant, $input);
                $this->createStarterCatalog($tenant, $location, $sector);

                $admin = User::query()->create([
                    'tenant_id' => $tenant->id,
                    'type' => UserType::TenantAdmin,
                    'email' => mb_strtolower(trim($input['admin_email'])),
                    'locale' => $input['locale'],
                    'status' => 'active',
                    'mfa_enforced' => true, // docs/14 §2
                    // The invite itself travels by email: ownership is
                    // implicitly proven at first password setup.
                    'email_verified_at' => now(),
                ]);

                $inviteToken = $this->createInviteToken($admin);

                return ['tenant' => $tenant, 'admin' => $admin, 'invite_token' => $inviteToken];
            });
        });

        $this->audit->log(
            'tenant.provisioned',
            $actorUserId,
            ['plan' => $plan->code, 'sector' => $sector],
            $result['tenant']->id,
            Tenant::class,
            $result['tenant']->id,
        );

        return $result;
    }

    /** @param array{display_name: string, timezone: string} $input */
    private function createDefaultLocation(Tenant $tenant, array $input): Location
    {
        $defaults = $this->config->get('booking.defaults');

        $location = Location::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $input['display_name'],
            'timezone' => $input['timezone'],
            'status' => 'active',
            ...$defaults,
        ]);

        foreach ($this->config->get('booking.default_weekly_schedule') as $rule) {
            $location->schedules()->create([
                'tenant_id' => $tenant->id,
                'weekday' => $rule['weekday'],
                'start_time' => $rule['start'],
                'end_time' => $rule['end'],
            ]);
        }

        return $location;
    }

    private function createStarterCatalog(Tenant $tenant, Location $location, string $sector): void
    {
        $presets = $this->config->get("sector_presets.{$sector}", $this->config->get('sector_presets.other'));
        $categories = [];

        foreach ($presets as $preset) {
            $categoryName = $preset['category'];

            $categories[$categoryName] ??= ServiceCategory::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $categoryName,
            ]);

            $service = Service::query()->create([
                'tenant_id' => $tenant->id,
                'category_id' => $categories[$categoryName]->id,
                'name' => $preset['service'],
                'is_active' => true,
            ]);

            $service->variants()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Standard',
                'duration_minutes' => $preset['duration'],
                'buffer_after_minutes' => $preset['buffer'],
                'price_cents' => $preset['price'],
                'currency' => 'EUR',
                'is_default' => true,
            ]);

            $location->services()->attach($service->id, ['tenant_id' => $tenant->id]);
        }
    }

    /** Invitation token: the admin sets the password on first access. */
    private function createInviteToken(User $admin): string
    {
        $token = Str::random(64);

        $this->db->table('password_reset_tokens')->updateOrInsert(
            ['email' => $admin->email],
            ['token' => hash('sha256', $token), 'created_at' => now()],
        );

        return $token;
    }
}
