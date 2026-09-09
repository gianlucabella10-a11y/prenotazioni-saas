<?php

declare(strict_types=1);

namespace App\Modules\Branding\Application;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\Branding\Infrastructure\Models\BrandAsset;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use GdImage;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Storage;

/**
 * Asset Factory: dal logo master del tenant genera i derivati raster (icone
 * Android/iOS, splash, store assets) ridimensionando con GD — **nessun nuovo
 * stack**. Ogni derivato è una riga brand_assets (kind, variant, version,
 * is_current). **Storico**: ogni rigenerazione incrementa la version e marca
 * `is_current` solo i nuovi derivati, SENZA cancellare i precedenti (rollback
 * possibile via RollbackBrandAssets). **Graceful**: senza logo o con formato
 * non rasterizzabile (es. SVG) non genera nulla (no eccezioni).
 */
final readonly class GenerateBrandAssets
{
    /** Icone quadrate (px) per variante. */
    private const ICONS = [
        'android_mdpi' => 48,
        'android_hdpi' => 72,
        'android_xhdpi' => 96,
        'android_xxhdpi' => 144,
        'android_xxxhdpi' => 192,
        'ios_60' => 60,
        'ios_120' => 120,
        'ios_180' => 180,
        'ios_1024' => 1024,
    ];

    /** Splash quadrato (logo centrato su sfondo brand). */
    private const SPLASH = [
        'splash_1x' => 480,
        'splash_2x' => 960,
        'splash_3x' => 1440,
    ];

    /** Store assets non quadrati [larghezza, altezza]. */
    private const STORE = [
        'feature_graphic' => [1024, 500],   // Google Play feature graphic
        'screenshot_phone' => [1080, 1920], // placeholder brandizzato
    ];

    /** Favicon per build web/PWA: quadrate, sfondo trasparente come le icone. */
    private const FAVICON = [
        'favicon_32' => 32,
        'favicon_16' => 16,
    ];

    public function __construct(
        private CurrentTenant $currentTenant,
        private Config $config,
    ) {}

    /** @return list<BrandAsset> derivati generati (vuoto se logo assente/non valido) */
    public function execute(int $tenantId): array
    {
        return $this->currentTenant->bypass(function () use ($tenantId): array {
            $brand = BrandProfile::query()->where('tenant_id', $tenantId)->first();

            if ($brand === null) {
                return [];
            }

            $source = BrandAsset::query()
                ->where('brand_profile_id', $brand->id)
                ->whereIn('kind', [BrandAsset::KIND_ICON_SOURCE, BrandAsset::KIND_LOGO])
                ->orderByRaw("kind = '".BrandAsset::KIND_ICON_SOURCE."' desc")
                ->first();

            $disk = (string) $this->config->get('branding.asset_disk', 'public');

            if ($source === null || ! Storage::disk($disk)->exists($source->disk_path)) {
                return [];
            }

            $image = @imagecreatefromstring(Storage::disk($disk)->get($source->disk_path));

            if (! $image instanceof GdImage) {
                return []; // formato non rasterizzabile (es. SVG) → graceful
            }

            $version = (int) (BrandAsset::query()
                ->where('brand_profile_id', $brand->id)
                ->whereNotNull('variant')
                ->max('version') ?? 0) + 1;

            // STORICO: non cancellare. Le versioni precedenti restano per
            // rollback, ma smettono di essere "correnti".
            BrandAsset::query()
                ->where('brand_profile_id', $brand->id)
                ->whereNotNull('variant')
                ->update(['is_current' => false]);

            $bg = $this->hexToRgb($brand->primary_color ?? '#1F2937');
            $generated = [];

            foreach (self::ICONS as $variant => $size) {
                $generated[] = $this->render($image, $brand->id, $disk, 'icon', $variant, $size, $size, $version, null, 0.12);
            }

            foreach (self::SPLASH as $variant => $size) {
                $generated[] = $this->render($image, $brand->id, $disk, 'splash', $variant, $size, $size, $version, $bg, 0.30);
            }

            foreach (self::STORE as $variant => [$width, $height]) {
                $kind = $variant === 'feature_graphic' ? 'feature_graphic' : 'screenshot';
                $generated[] = $this->render($image, $brand->id, $disk, $kind, $variant, $width, $height, $version, $bg, 0.28);
            }

            foreach (self::FAVICON as $variant => $size) {
                $generated[] = $this->render($image, $brand->id, $disk, 'favicon', $variant, $size, $size, $version, null, 0.06);
            }

            imagedestroy($image);

            return $generated;
        });
    }

    /** @param array{0:int,1:int,2:int}|null $bg background rgb (null = trasparente) */
    private function render(GdImage $source, int $brandId, string $disk, string $kind, string $variant, int $width, int $height, int $version, ?array $bg, float $padRatio): BrandAsset
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        if ($bg === null) {
            imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        } else {
            imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, $bg[0], $bg[1], $bg[2]));
        }

        imagealphablending($canvas, true);

        $sw = imagesx($source);
        $sh = imagesy($source);
        $pad = (int) round(min($width, $height) * $padRatio);
        $boxW = $width - 2 * $pad;
        $boxH = $height - 2 * $pad;
        $scale = min($boxW / $sw, $boxH / $sh);
        $dw = (int) round($sw * $scale);
        $dh = (int) round($sh * $scale);
        imagecopyresampled($canvas, $source, (int) (($width - $dw) / 2), (int) (($height - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();
        imagedestroy($canvas);

        $path = "brand/{$brandId}/generated/v{$version}/{$kind}-{$variant}.png";
        Storage::disk($disk)->put($path, $png);

        return BrandAsset::query()->create([
            'brand_profile_id' => $brandId,
            'kind' => $kind,
            'variant' => $variant,
            'version' => $version,
            'is_current' => true,
            'disk_path' => $path,
            'mime' => 'image/png',
            'width' => $width,
            'height' => $height,
            'checksum' => hash('sha256', $png),
        ]);
    }

    /** @return array{0:int,1:int,2:int} */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            return [31, 41, 55];
        }

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }
}
