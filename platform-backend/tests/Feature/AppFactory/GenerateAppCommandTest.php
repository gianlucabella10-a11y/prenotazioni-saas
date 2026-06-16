<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\Branding\Infrastructure\Models\BrandAsset;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class GenerateAppCommandTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function putLogo(Tenant $tenant): void
    {
        $brandId = $this->bypassTenancy(fn (): int => BrandProfile::query()->where('tenant_id', $tenant->id)->firstOrFail()->id);

        $img = imagecreatetruecolor(64, 64);
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

    public function test_command_prepares_app_ready_to_build_with_logo(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $env = $this->provisionBookableTenant();
        $this->bypassTenancy(fn () => User::factory()->superAdmin()->create());
        $this->putLogo($env['tenant']);

        $this->artisan('app:generate', ['tenant' => $env['tenant']->uuid])->assertSuccessful();

        $project = $this->bypassTenancy(fn (): AppProject => AppProject::query()->where('tenant_id', $env['tenant']->id)->firstOrFail());
        self::assertSame('ready_to_build', $project->build_status->value);
        self::assertSame(1, $this->bypassTenancy(fn () => AppBuild::query()->where('app_project_id', $project->id)->count()));
    }

    public function test_command_without_logo_stays_generated(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $env = $this->provisionBookableTenant();

        $this->artisan('app:generate', ['tenant' => $env['tenant']->uuid])->assertSuccessful();

        $project = $this->bypassTenancy(fn (): AppProject => AppProject::query()->where('tenant_id', $env['tenant']->id)->firstOrFail());
        self::assertSame('generated', $project->build_status->value);
    }

    public function test_command_fails_for_unknown_tenant(): void
    {
        $this->artisan('app:generate', ['tenant' => 'no-such-uuid'])->assertFailed();
    }
}
