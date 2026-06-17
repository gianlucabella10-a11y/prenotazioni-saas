<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Modules\Branding\Application\GenerateBrandAssets;
use App\Modules\Branding\Infrastructure\Models\BrandAsset;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class GenerateBrandAssetsTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function brandId(Tenant $tenant): int
    {
        return $this->bypassTenancy(fn (): int => BrandProfile::query()->where('tenant_id', $tenant->id)->firstOrFail()->id);
    }

    private function putLogo(int $brandId, string $bytes, string $mime = 'image/png'): void
    {
        $path = "brand/{$brandId}/logo.".($mime === 'image/svg+xml' ? 'svg' : 'png');
        Storage::disk('public')->put($path, $bytes);

        $this->bypassTenancy(fn () => BrandAsset::query()->create([
            'brand_profile_id' => $brandId,
            'kind' => BrandAsset::KIND_LOGO,
            'disk_path' => $path,
            'mime' => $mime,
        ]));
    }

    private function pngBytes(): string
    {
        $img = imagecreatetruecolor(120, 120);
        imagefilledrectangle($img, 0, 0, 120, 120, imagecolorallocate($img, 10, 30, 60));
        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    public function test_generates_icon_and_splash_set_from_logo(): void
    {
        Storage::fake('public');
        $env = $this->provisionBookableTenant();
        $brandId = $this->brandId($env['tenant']);
        $this->putLogo($brandId, $this->pngBytes());

        $generated = app(GenerateBrandAssets::class)->execute($env['tenant']->id);

        self::assertCount(14, $generated); // 9 icone + 3 splash + 2 store
        $derived = $this->bypassTenancy(fn () => BrandAsset::query()->where('brand_profile_id', $brandId)->whereNotNull('variant')->get());
        self::assertCount(14, $derived);
        self::assertTrue($derived->every(fn (BrandAsset $a): bool => Storage::disk('public')->exists($a->disk_path)));
        self::assertTrue($derived->every(fn (BrandAsset $a): bool => (bool) $a->is_current));
        self::assertSame(1, $derived->max('version'));
        // Store assets generati (feature graphic + screenshot placeholder).
        self::assertSame(1, $derived->where('kind', 'feature_graphic')->count());
        self::assertSame(1, $derived->where('kind', 'screenshot')->count());
    }

    public function test_regeneration_keeps_history_and_bumps_version(): void
    {
        Storage::fake('public');
        $env = $this->provisionBookableTenant();
        $brandId = $this->brandId($env['tenant']);
        $this->putLogo($brandId, $this->pngBytes());

        app(GenerateBrandAssets::class)->execute($env['tenant']->id);
        app(GenerateBrandAssets::class)->execute($env['tenant']->id);

        $derived = $this->bypassTenancy(fn () => BrandAsset::query()->where('brand_profile_id', $brandId)->whereNotNull('variant')->get());
        self::assertCount(28, $derived); // storico mantenuto (14 v1 + 14 v2), MAI cancellato
        self::assertSame(14, $derived->where('is_current', true)->count()); // solo v2 corrente
        self::assertSame(2, $derived->max('version'));
        self::assertTrue($derived->where('version', 1)->every(fn (BrandAsset $a): bool => ! $a->is_current));
    }

    public function test_missing_logo_is_graceful(): void
    {
        Storage::fake('public');
        $env = $this->provisionBookableTenant();

        $generated = app(GenerateBrandAssets::class)->execute($env['tenant']->id);

        self::assertSame([], $generated);
    }

    public function test_non_rasterizable_logo_is_skipped(): void
    {
        Storage::fake('public');
        $env = $this->provisionBookableTenant();
        $brandId = $this->brandId($env['tenant']);
        $this->putLogo($brandId, '<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'image/svg+xml');

        $generated = app(GenerateBrandAssets::class)->execute($env['tenant']->id);

        self::assertSame([], $generated);
    }

    public function test_assets_are_isolated_per_tenant(): void
    {
        Storage::fake('public');
        $a = $this->provisionBookableTenant();
        $b = $this->provisionBookableTenant();
        $this->putLogo($this->brandId($a['tenant']), $this->pngBytes());

        app(GenerateBrandAssets::class)->execute($a['tenant']->id);

        $bDerived = $this->bypassTenancy(fn () => BrandAsset::query()->where('brand_profile_id', $this->brandId($b['tenant']))->whereNotNull('variant')->count());
        self::assertSame(0, $bDerived);
    }
}
