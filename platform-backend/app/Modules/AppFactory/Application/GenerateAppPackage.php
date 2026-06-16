<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Domain\AppProjectStatus;
use App\Modules\AppFactory\Domain\TemplateRegistry;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\Branding\Infrastructure\Models\BrandAsset;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Storage;

/**
 * App Generator (FASE 2A) — Build Manifest 2.0: la sorgente unica di verità
 * per la futura build. Sezioni: app_identity, runtime (dart_define), branding
 * (logo/icone/splash/colori, con i path dei derivati generati), template,
 * assets, metadata. **Nessuna build nativa.** Lo stato diventa
 * `ready_to_build` se gli asset sono presenti, altrimenti `generated`.
 */
final readonly class GenerateAppPackage
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private Config $config,
        private AuditLogger $audit,
        private TemplateRegistry $templates,
    ) {}

    public function execute(AppProject $project, ?int $actorUserId): AppBuild
    {
        return $this->currentTenant->bypass(function () use ($project, $actorUserId): AppBuild {
            $tenant = Tenant::query()->findOrFail($project->tenant_id);
            $brand = BrandProfile::query()->where('tenant_id', $tenant->id)->first();

            $logo = $brand === null ? null : BrandAsset::query()
                ->where('brand_profile_id', $brand->id)
                ->where('kind', BrandAsset::KIND_LOGO)
                ->first();

            $derived = $brand === null ? collect() : BrandAsset::query()
                ->where('brand_profile_id', $brand->id)
                ->whereNotNull('variant')
                ->get();

            $template = $this->templates->get($project->template_code);
            $next = AppBuild::query()->where('app_project_id', $project->id)->count() + 1;
            $version = "1.0.0+{$next}";
            $hasAssets = $derived->isNotEmpty();

            $manifest = [
                'app_identity' => [
                    'name' => $brand?->app_name,
                    'slug' => $project->slug,
                    'bundle_id' => $project->bundle_id,
                    'package_name' => $project->package_name,
                    'store_name' => $project->store_name,
                ],
                'runtime' => [
                    'env' => 'production',
                    'api_url' => (string) $this->config->get('app_factory.api_base_url'),
                    'tenant_key' => $tenant->api_key,
                    'dart_define' => [
                        'ENV' => 'production',
                        'API_BASE_URL' => (string) $this->config->get('app_factory.api_base_url'),
                        'TENANT_KEY' => $tenant->api_key,
                        'APP_NAME' => $brand?->app_name,
                        'TEMPLATE' => $project->template_code,
                    ],
                ],
                'branding' => [
                    'logo' => $logo?->disk_path,
                    'icons' => $derived->where('kind', 'icon')
                        ->map(fn (BrandAsset $a): array => ['variant' => $a->variant, 'path' => $a->disk_path, 'size' => $a->width])
                        ->values()->all(),
                    'splash' => $derived->where('kind', 'splash')
                        ->map(fn (BrandAsset $a): array => ['variant' => $a->variant, 'path' => $a->disk_path, 'size' => $a->width])
                        ->values()->all(),
                    'colors' => [
                        'primary' => $brand?->primary_color,
                        'secondary' => $brand?->secondary_color,
                    ],
                ],
                'template' => [
                    'template_code' => $project->template_code,
                    'layout_variant' => $template['layout_variant'] ?? 'standard',
                    'font_style' => $project->font_style,
                    'sections' => $template['sections'] ?? [],
                ],
                'assets' => $derived
                    ->map(fn (BrandAsset $a): array => ['kind' => $a->kind, 'variant' => $a->variant, 'path' => $a->disk_path, 'version' => $a->version])
                    ->values()->all(),
                'metadata' => [
                    'manifest_schema' => '2.0',
                    'version' => $version,
                    'config_version' => $brand?->config_version,
                    'generated_at' => now()->toIso8601ZuluString(),
                ],
            ];

            $disk = (string) $this->config->get('app_factory.manifest_disk', 'local');
            $path = "app_factory/{$tenant->uuid}/manifest-v{$next}.json";
            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            Storage::disk($disk)->put($path, $json);
            // Puntatore stabile all'ultima versione (usato dalla pipeline CI, FASE 2C).
            Storage::disk($disk)->put("app_factory/{$tenant->uuid}/manifest-latest.json", $json);

            $build = AppBuild::query()->create([
                'tenant_id' => $tenant->id,
                'app_project_id' => $project->id,
                'version' => $version,
                'platform' => 'config',
                'status' => 'generated',
                'artifact_path' => $path,
                'created_by' => $actorUserId,
            ]);

            $project->forceFill([
                'build_status' => $hasAssets ? AppProjectStatus::ReadyToBuild : AppProjectStatus::Generated,
                'last_generated_at' => now(),
                'build_manifest' => $manifest,
            ])->save();

            $this->audit->log(
                'app_project.generated',
                $actorUserId,
                ['version' => $version, 'assets' => $derived->count()],
                $tenant->id,
                AppProject::class,
                $project->id,
            );

            return $build;
        });
    }
}
