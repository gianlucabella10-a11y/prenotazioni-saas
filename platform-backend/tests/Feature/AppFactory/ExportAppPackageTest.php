<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Application\PrepareApp;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\Branding\Infrastructure\Models\BrandAsset;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class ExportAppPackageTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function putLogo(Tenant $tenant): void
    {
        $brandId = $this->bypassTenancy(fn (): int => BrandProfile::query()->where('tenant_id', $tenant->id)->firstOrFail()->id);

        $img = imagecreatetruecolor(256, 256);
        imagefilledrectangle($img, 0, 0, 256, 256, imagecolorallocate($img, 12, 30, 60));
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        Storage::disk('public')->put("brand/{$brandId}/logo.png", $png);
        $this->bypassTenancy(fn () => BrandAsset::query()->create([
            'brand_profile_id' => $brandId,
            'kind' => BrandAsset::KIND_LOGO,
            'disk_path' => "brand/{$brandId}/logo.png",
            'mime' => 'image/png',
        ]));
    }

    private function prepare(Tenant $tenant, string $template = 'barber_dark'): AppProject
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $this->putLogo($tenant);
        $project = app(AllocateAppIdentifiers::class)->execute($tenant, $template, $admin->id);
        app(PrepareApp::class)->execute($project, $admin->id);

        return $project;
    }

    public function test_prepare_assembles_self_contained_package(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $env = $this->provisionBookableTenant();

        $project = $this->prepare($env['tenant']);

        $base = "generated_apps/{$env['tenant']->uuid}";
        Storage::disk('local')->assertExists("{$base}/manifest.json");
        Storage::disk('local')->assertExists("{$base}/config.json");
        Storage::disk('local')->assertExists("{$base}/README.txt");
        Storage::disk('local')->assertExists("{$base}/config/build.env");
        Storage::disk('local')->assertExists("{$base}/config/template.json");
        self::assertNotEmpty(Storage::disk('local')->files("{$base}/assets"));

        // config.json operatore-facing: contiene l'identità dell'app.
        $config = json_decode((string) Storage::disk('local')->get("{$base}/config.json"), true);
        self::assertSame($project->bundle_id, $config['app_identity']['bundle_id']);
        self::assertSame($env['tenant']->uuid, $config['tenant_uuid']);
    }

    public function test_package_is_isolated_per_tenant(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $a = $this->provisionBookableTenant();
        $b = $this->provisionBookableTenant();

        $this->prepare($a['tenant']);

        self::assertFalse(Storage::disk('local')->exists("generated_apps/{$b['tenant']->uuid}/manifest.json"));
    }

    public function test_super_admin_downloads_package_zip(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $env = $this->provisionBookableTenant();
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $this->putLogo($env['tenant']);
        $project = app(AllocateAppIdentifiers::class)->execute($env['tenant'], 'default', $admin->id);
        app(PrepareApp::class)->execute($project, $admin->id);

        $this->actingAs($admin, 'admin')
            ->get(route('control.apps.package', $project->uuid))
            ->assertOk()
            ->assertDownload("app-{$project->uuid}.zip");
    }

    public function test_show_renders_brand_preview(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $env = $this->provisionBookableTenant();
        $project = $this->prepare($env['tenant']);

        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());

        $this->actingAs($admin, 'admin')
            ->get("/control-room/apps/{$project->uuid}")
            ->assertOk()
            ->assertSee('Anteprima brand');
    }
}
