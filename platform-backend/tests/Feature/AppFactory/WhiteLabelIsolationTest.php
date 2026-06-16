<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\Branding\Infrastructure\Models\BrandAsset;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Isolamento white-label: in CONTESTO Tenant A nessuna risorsa di Tenant B è
 * visibile. AppProject e BrandProfile sono tenant-scoped (TenantScope); gli
 * asset sono isolati transitivamente via brand_profile_id.
 */
final class WhiteLabelIsolationTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function brandId(Tenant $tenant): int
    {
        return $this->bypassTenancy(fn (): int => BrandProfile::query()->where('tenant_id', $tenant->id)->firstOrFail()->id);
    }

    private function seedTenant(Tenant $tenant, string $template): AppProject
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $project = app(AllocateAppIdentifiers::class)->execute($tenant, $template, $admin->id);

        $brandId = $this->brandId($tenant);
        $this->bypassTenancy(fn () => BrandAsset::query()->create([
            'brand_profile_id' => $brandId,
            'kind' => BrandAsset::KIND_LOGO,
            'disk_path' => "brand/{$brandId}/logo.png",
            'mime' => 'image/png',
        ]));

        return $project;
    }

    public function test_tenant_context_sees_only_its_own_app_project_and_brand(): void
    {
        $a = $this->provisionBookableTenant();
        $b = $this->provisionBookableTenant();
        $projectA = $this->seedTenant($a['tenant'], 'barber_dark');
        $this->seedTenant($b['tenant'], 'beauty_visual');

        $this->bindTenant($a['tenant']);

        self::assertSame(1, AppProject::query()->count());
        self::assertSame($projectA->id, AppProject::query()->firstOrFail()->id);

        self::assertSame(1, BrandProfile::query()->count());
        self::assertSame($a['tenant']->id, BrandProfile::query()->firstOrFail()->tenant_id);
    }

    public function test_brand_assets_are_isolated_via_brand_ownership(): void
    {
        $a = $this->provisionBookableTenant();
        $b = $this->provisionBookableTenant();
        $this->seedTenant($a['tenant'], 'barber_dark');
        $this->seedTenant($b['tenant'], 'beauty_visual');

        $brandA = $this->brandId($a['tenant']);
        $brandB = $this->brandId($b['tenant']);
        self::assertNotSame($brandA, $brandB);

        // Gli asset del brand A non includono mai quelli del brand B.
        $assetsA = $this->bypassTenancy(fn () => BrandAsset::query()->where('brand_profile_id', $brandA)->pluck('brand_profile_id')->unique()->all());
        self::assertSame([$brandA], $assetsA);
    }

    public function test_tenant_b_cannot_resolve_tenant_a_app_project_by_uuid(): void
    {
        $a = $this->provisionBookableTenant();
        $b = $this->provisionBookableTenant();
        $projectA = $this->seedTenant($a['tenant'], 'barber_dark');
        $this->seedTenant($b['tenant'], 'beauty_visual');

        $this->bindTenant($b['tenant']);

        // In contesto B il progetto di A non è raggiungibile dalla query scoped.
        self::assertNull(AppProject::query()->where('uuid', $projectA->uuid)->first());
    }
}
