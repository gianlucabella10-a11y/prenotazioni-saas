<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\Branding\Infrastructure\Models\BrandAsset;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Dati di anteprima del brand/app per la Control Room (logo, swatch colori,
 * mockup icona, stato asset, etichetta ciclo di vita). Tiene la logica di
 * lettura fuori dal controller. Solo lettura, cross-tenant via bypass.
 */
final readonly class AppPreview
{
    private const STATUS_LABELS = [
        'draft' => 'Bozza',
        'configured' => 'Configurata',
        'ready' => 'Pronta',
        'generated' => 'Generata',
        'ready_to_build' => 'Pronta build',
        'building' => 'In build',
        'built' => 'Compilata',
        'published' => 'Pubblicata',
        'failed' => 'Fallita',
    ];

    public function __construct(
        private CurrentTenant $currentTenant,
        private Config $config,
    ) {}

    /** @return array<string, mixed> */
    public function execute(AppProject $project): array
    {
        return $this->currentTenant->bypass(function () use ($project): array {
            $brand = BrandProfile::query()->where('tenant_id', $project->tenant_id)->first();
            $disk = Storage::disk((string) $this->config->get('branding.asset_disk', 'public'));

            $logo = $brand === null ? null : BrandAsset::query()
                ->where('brand_profile_id', $brand->id)
                ->where('kind', BrandAsset::KIND_LOGO)
                ->first();

            // Solo i derivati della versione CORRENTE (storico escluso).
            $derived = $brand === null ? collect() : BrandAsset::query()
                ->where('brand_profile_id', $brand->id)
                ->whereNotNull('variant')
                ->where('is_current', true)
                ->get();

            // Mockup icona: preferisci ios_1024, altrimenti l'icona più grande.
            $icon = $derived->where('kind', 'icon')->sortByDesc('width')
                ->sortByDesc(fn (BrandAsset $a): int => $a->variant === 'ios_1024' ? 1 : 0)
                ->first();
            $splash = $derived->where('kind', 'splash')->sortByDesc('width')->first();
            $feature = $derived->where('kind', 'feature_graphic')->first();

            $status = $project->build_status->value;

            return [
                'logo_url' => $this->urlFor($disk, $logo?->disk_path),
                'icon_url' => $this->urlFor($disk, $icon?->disk_path),
                'splash_url' => $this->urlFor($disk, $splash?->disk_path),
                'feature_url' => $this->urlFor($disk, $feature?->disk_path),
                'colors' => [
                    'primary' => $brand?->primary_color,
                    'secondary' => $brand?->secondary_color,
                ],
                'assets' => [
                    'icons' => $derived->where('kind', 'icon')->count(),
                    'splash' => $derived->where('kind', 'splash')->count(),
                    'store' => $derived->whereIn('kind', ['feature_graphic', 'screenshot'])->count(),
                    'version' => (int) ($derived->max('version') ?? 0),
                ],
                'lifecycle' => $status === 'draft' && $logo !== null
                    ? 'Configurata'
                    : (self::STATUS_LABELS[$status] ?? $status),
            ];
        });
    }

    private function urlFor(Filesystem $disk, ?string $path): ?string
    {
        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        try {
            return $disk->url($path);
        } catch (\Throwable) {
            return null; // disco senza URL pubblico
        }
    }
}
