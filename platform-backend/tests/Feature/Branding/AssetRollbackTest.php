<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Modules\Branding\Application\GenerateBrandAssets;
use App\Modules\Branding\Application\RollbackBrandAssets;
use App\Modules\Branding\Infrastructure\Models\BrandAsset;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class AssetRollbackTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function seedLogoAndGenerateTwice(Tenant $tenant): void
    {
        $brandId = $this->bypassTenancy(fn (): int => BrandProfile::query()->where('tenant_id', $tenant->id)->firstOrFail()->id);

        $img = imagecreatetruecolor(256, 256);
        imagefilledrectangle($img, 0, 0, 256, 256, imagecolorallocate($img, 20, 40, 80));
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

        app(GenerateBrandAssets::class)->execute($tenant->id); // v1
        app(GenerateBrandAssets::class)->execute($tenant->id); // v2 (corrente)
    }

    private function currentVersions(Tenant $tenant): array
    {
        return $this->bypassTenancy(function () use ($tenant): array {
            $brandId = BrandProfile::query()->where('tenant_id', $tenant->id)->firstOrFail()->id;

            return BrandAsset::query()->where('brand_profile_id', $brandId)
                ->whereNotNull('variant')->where('is_current', true)
                ->pluck('version')->unique()->values()->all();
        });
    }

    public function test_rollback_restores_previous_version(): void
    {
        Storage::fake('public');
        $env = $this->provisionBookableTenant();
        $this->seedLogoAndGenerateTwice($env['tenant']);

        self::assertSame([2], $this->currentVersions($env['tenant'])); // v2 corrente

        $ok = app(RollbackBrandAssets::class)->execute($env['tenant']->id, 1);

        self::assertTrue($ok);
        self::assertSame([1], $this->currentVersions($env['tenant'])); // ripristinata v1
    }

    public function test_rollback_unknown_version_is_graceful(): void
    {
        Storage::fake('public');
        $env = $this->provisionBookableTenant();
        $this->seedLogoAndGenerateTwice($env['tenant']);

        self::assertFalse(app(RollbackBrandAssets::class)->execute($env['tenant']->id, 99));
        self::assertSame([2], $this->currentVersions($env['tenant'])); // invariato
    }

    public function test_versions_lists_full_history(): void
    {
        Storage::fake('public');
        $env = $this->provisionBookableTenant();
        $this->seedLogoAndGenerateTwice($env['tenant']);

        $versions = app(RollbackBrandAssets::class)->versions($env['tenant']->id);

        self::assertCount(2, $versions);
        self::assertSame(2, $versions[0]['version']);
        self::assertTrue($versions[0]['current']);
        self::assertFalse($versions[1]['current']);
    }
}
