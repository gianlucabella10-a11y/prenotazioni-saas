<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Storage;

/**
 * Assembla il pacchetto app self-contained del tenant — la cartella che un
 * operatore (o la CI) consegna al runner di build, senza intervento tecnico:
 *
 *   generated_apps/{uuid}/
 *     manifest.json          (manifest 2.0 completo)
 *     assets/                (logo + icone + splash derivate)
 *     config/build.env       (--dart-define)
 *     config/template.json   (skin: layout/font/sezioni)
 *
 * Idempotente (ricostruisce da zero) e isolato per tenant. Legge il manifest
 * già prodotto da GenerateAppPackage (`manifest-latest.json`): nessuna logica
 * di build qui.
 */
final readonly class ExportAppPackage
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private Config $config,
    ) {}

    /** @return string|null path base della cartella esportata (null se manifest assente) */
    public function execute(AppProject $project): ?string
    {
        return $this->currentTenant->bypass(function () use ($project): ?string {
            $tenant = Tenant::query()->findOrFail($project->tenant_id);

            $manifestDisk = (string) $this->config->get('app_factory.manifest_disk', 'local');
            $manifestPath = "app_factory/{$tenant->uuid}/manifest-latest.json";

            if (! Storage::disk($manifestDisk)->exists($manifestPath)) {
                return null;
            }

            $json = (string) Storage::disk($manifestDisk)->get($manifestPath);
            /** @var array<string, mixed> $manifest */
            $manifest = (array) json_decode($json, true);

            $exportDisk = (string) $this->config->get('app_factory.export_disk', $manifestDisk);
            $base = trim((string) $this->config->get('app_factory.export_base', 'generated_apps'), '/')."/{$tenant->uuid}";

            // Idempotente: la cartella riflette sempre l'ultima generazione.
            Storage::disk($exportDisk)->deleteDirectory($base);

            Storage::disk($exportDisk)->put("{$base}/manifest.json", $json);

            // assets/ — copia logo + derivati dal disco branding.
            $assetDisk = (string) $this->config->get('branding.asset_disk', 'public');

            foreach ($this->assetPaths($manifest) as $relPath) {
                if ($relPath !== '' && Storage::disk($assetDisk)->exists($relPath)) {
                    Storage::disk($exportDisk)->put(
                        "{$base}/assets/".basename($relPath),
                        Storage::disk($assetDisk)->get($relPath),
                    );
                }
            }

            // config/ — dart-define di build + skin del template.
            $dartDefine = (array) ($manifest['runtime']['dart_define'] ?? []);
            $env = '';

            foreach ($dartDefine as $key => $value) {
                $env .= "{$key}={$value}\n";
            }

            Storage::disk($exportDisk)->put("{$base}/config/build.env", $env);
            Storage::disk($exportDisk)->put(
                "{$base}/config/template.json",
                (string) json_encode($manifest['template'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            );

            // config.json (operatore-facing): identità + branding + environment + build.
            $identity = (array) ($manifest['app_identity'] ?? []);
            $branding = (array) ($manifest['branding'] ?? []);

            Storage::disk($exportDisk)->put("{$base}/config.json", (string) json_encode([
                'tenant_uuid' => $tenant->uuid,
                'app_identity' => $identity,
                'environment' => $dartDefine,
                'branding' => [
                    'colors' => $branding['colors'] ?? [],
                    'logo' => $branding['logo'] ?? null,
                ],
                'template' => $manifest['template'] ?? [],
                'build' => $manifest['metadata'] ?? [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            Storage::disk($exportDisk)->put("{$base}/README.txt", $this->readme($identity));

            return $base;
        });
    }

    /** @param array<string, mixed> $identity */
    private function readme(array $identity): string
    {
        $name = (string) ($identity['name'] ?? $identity['store_name'] ?? 'App');
        $bundle = (string) ($identity['bundle_id'] ?? '');
        $package = (string) ($identity['package_name'] ?? '');

        return <<<TXT
        Pacchetto app white-label — {$name}

        Contenuto:
          manifest.json         sorgente di verità (identità, branding, template, asset, metadata)
          config.json           riepilogo operatore (identità + branding + environment + build)
          assets/               logo + icone + splash derivate dal logo master
          config/build.env      variabili --dart-define per la build
          config/template.json  skin (layout / font / sezioni)

        Uso (su Mac/CI — NON in questo ambiente, nessuna build qui):
          1. genera icone/splash dagli assets/ (flutter_launcher_icons / flutter_native_splash)
          2. Android: flutter build appbundle --release -PAPP_ID={$package} -PAPP_NAME="{$name}" \\
               --dart-define-from-file=config/build.env
          3. iOS: bundle id {$bundle} + display name "{$name}"
             (orchestrato da tool/app_factory/make_app.sh)

        Firma e upload store richiedono keystore/account: vedi APP_FACTORY_RELEASE_SECRETS.md.
        TXT;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    private function assetPaths(array $manifest): array
    {
        $branding = (array) ($manifest['branding'] ?? []);
        $paths = [];

        if (is_string($branding['logo'] ?? null)) {
            $paths[] = $branding['logo'];
        }

        foreach (['icons', 'splash'] as $group) {
            foreach ((array) ($branding[$group] ?? []) as $item) {
                if (is_array($item) && is_string($item['path'] ?? null)) {
                    $paths[] = $item['path'];
                }
            }
        }

        return array_values(array_unique($paths));
    }
}
