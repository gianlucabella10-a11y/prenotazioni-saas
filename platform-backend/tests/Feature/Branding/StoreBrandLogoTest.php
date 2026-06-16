<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Modules\Branding\Application\StoreBrandLogo;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class StoreBrandLogoTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function brand(): BrandProfile
    {
        $env = $this->provisionBookableTenant();

        return $this->bypassTenancy(fn (): BrandProfile => BrandProfile::query()->where('tenant_id', $env['tenant']->id)->firstOrFail());
    }

    public function test_accepts_valid_raster_logo(): void
    {
        Storage::fake('public');

        $asset = app(StoreBrandLogo::class)->store($this->brand(), UploadedFile::fake()->image('logo.png', 512, 512));

        self::assertSame('logo', $asset->kind);
        Storage::disk('public')->assertExists($asset->disk_path);
    }

    public function test_rejects_logo_below_min_side(): void
    {
        Storage::fake('public');

        $this->expectException(ValidationException::class);
        app(StoreBrandLogo::class)->store($this->brand(), UploadedFile::fake()->image('logo.png', 64, 64));
    }

    public function test_rejects_non_image_file(): void
    {
        Storage::fake('public');

        $this->expectException(ValidationException::class);
        app(StoreBrandLogo::class)->store($this->brand(), UploadedFile::fake()->create('logo.pdf', 12, 'application/pdf'));
    }
}
