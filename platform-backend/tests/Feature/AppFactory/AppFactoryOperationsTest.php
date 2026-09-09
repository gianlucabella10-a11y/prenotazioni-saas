<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Application\PrepareApp;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\Branding\Application\GenerateBrandAssets;
use App\Modules\Branding\Infrastructure\Models\BrandAsset;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Flusso operativo Control Room (HTTP): un super-admin avvia una build e fa
 * rollback degli asset, senza toccare codice. Verifica permessi e validazioni.
 */
final class AppFactoryOperationsTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function actingSuperAdmin(): User
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function projectFor(Tenant $tenant, int $adminId): AppProject
    {
        return app(AllocateAppIdentifiers::class)->execute($tenant, 'barber_dark', $adminId);
    }

    public function test_super_admin_dispatches_build_via_control_room(): void
    {
        config(['app_factory.build_driver' => 'manual']);
        Storage::fake('public');
        Storage::fake('local');
        $admin = $this->actingSuperAdmin();
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant'], $admin->id);
        app(PrepareApp::class)->execute($project, $admin->id); // pacchetto generato (prerequisito build)

        $this->post("/control-room/apps/{$project->uuid}/build", ['platform' => 'android'])->assertRedirect();

        self::assertSame(1, $this->bypassTenancy(fn (): int => AppBuild::query()
            ->where('app_project_id', $project->id)->where('status', 'building')->count()));
        self::assertSame('building', $this->bypassTenancy(fn (): string => AppProject::query()->findOrFail($project->id)->build_status->value));
    }

    public function test_build_rejects_invalid_platform(): void
    {
        $admin = $this->actingSuperAdmin();
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant'], $admin->id);

        $this->post("/control-room/apps/{$project->uuid}/build", ['platform' => 'web'])->assertSessionHasErrors('platform');
    }

    public function test_tenant_admin_cannot_dispatch_build(): void
    {
        $env = $this->provisionBookableTenant();
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $project = $this->projectFor($env['tenant'], $admin->id);
        $tenantAdmin = $this->createTenantAdmin($env['tenant']);

        $this->actingAs($tenantAdmin, 'admin')
            ->post("/control-room/apps/{$project->uuid}/build", ['platform' => 'android'])
            ->assertForbidden();
    }

    public function test_super_admin_rolls_back_assets_via_control_room(): void
    {
        Storage::fake('public');
        $admin = $this->actingSuperAdmin();
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant'], $admin->id);

        $brandId = $this->bypassTenancy(fn (): int => BrandProfile::query()->where('tenant_id', $env['tenant']->id)->firstOrFail()->id);
        $img = imagecreatetruecolor(256, 256);
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);
        Storage::disk('public')->put("brand/{$brandId}/logo.png", $png);
        $this->bypassTenancy(fn () => BrandAsset::query()->create([
            'brand_profile_id' => $brandId, 'kind' => BrandAsset::KIND_LOGO,
            'disk_path' => "brand/{$brandId}/logo.png", 'mime' => 'image/png',
        ]));
        app(GenerateBrandAssets::class)->execute($env['tenant']->id); // v1
        app(GenerateBrandAssets::class)->execute($env['tenant']->id); // v2 corrente

        $this->post("/control-room/apps/{$project->uuid}/rollback", ['version' => 1])->assertRedirect();

        $current = $this->bypassTenancy(fn (): array => BrandAsset::query()->where('brand_profile_id', $brandId)
            ->whereNotNull('variant')->where('is_current', true)->pluck('version')->unique()->values()->all());
        self::assertSame([1], $current);
    }
}
