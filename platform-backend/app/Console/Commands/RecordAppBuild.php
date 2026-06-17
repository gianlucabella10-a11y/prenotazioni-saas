<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Application\TransitionAppProject;
use App\Modules\AppFactory\Domain\AppProjectStatus;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Callback dalla pipeline CI (FASE 2C): registra l'esito di una build/firma/
 * pubblicazione per-tenant. NON esegue build — la CI invoca questo comando
 * (es. via SSH/artisan) per tracciare lo stato fino a `published`.
 */
final class RecordAppBuild extends Command
{
    protected $signature = 'app:build-record {tenant : UUID} {platform : android|ios} {status : building|built|published|failed} {--app-version=} {--artifact=} {--error=}';

    protected $description = 'Registra l\'esito di una build/pubblicazione per-tenant (callback CI). Nessuna build eseguita.';

    public function handle(CurrentTenant $current, TransitionAppProject $transition): int
    {
        $platform = (string) $this->argument('platform');
        $status = (string) $this->argument('status');

        if (! in_array($platform, ['android', 'ios'], true)) {
            $this->error('Piattaforma non valida (android|ios).');

            return self::FAILURE;
        }

        if (! in_array($status, ['building', 'built', 'published', 'failed'], true)) {
            $this->error('Stato non valido (building|built|published|failed).');

            return self::FAILURE;
        }

        $uuid = (string) $this->argument('tenant');
        $version = (string) ($this->option('app-version') ?? '');
        $artifact = $this->option('artifact');
        $error = $this->option('error');

        $result = $current->bypass(function () use ($uuid, $platform, $status, $version, $artifact, $error, $transition): ?array {
            $tenant = Tenant::query()->where('uuid', $uuid)->first();

            if ($tenant === null) {
                return null;
            }

            $project = AppProject::query()->where('tenant_id', $tenant->id)->first();

            if ($project === null) {
                return null;
            }

            $terminal = in_array($status, ['built', 'published', 'failed'], true);

            // Completa la build in volo (queued/building) per la stessa piattaforma,
            // così la timeline resta su un'unica riga; altrimenti ne crea una.
            $build = AppBuild::query()
                ->where('app_project_id', $project->id)
                ->where('platform', $platform)
                ->whereIn('status', ['queued', 'building'])
                ->orderByDesc('id')
                ->first();

            if ($build !== null) {
                $build->forceFill([
                    'status' => $status,
                    'artifact_path' => $artifact ?? $build->artifact_path,
                    'error_message' => $status === 'failed' ? $error : $build->error_message,
                    'finished_at' => $terminal ? now() : $build->finished_at,
                ] + ($version !== '' ? ['version' => $version] : []))->save();
            } else {
                $next = AppBuild::query()->where('app_project_id', $project->id)->count() + 1;

                $build = AppBuild::query()->create([
                    'tenant_id' => $tenant->id,
                    'app_project_id' => $project->id,
                    'version' => $version !== '' ? $version : "1.0.0+{$next}",
                    'platform' => $platform,
                    'status' => $status,
                    'artifact_path' => $artifact,
                    'error_message' => $status === 'failed' ? $error : null,
                    'finished_at' => $terminal ? now() : null,
                ]);
            }

            // Release train: alla pubblicazione fissa il core di build.
            if ($status === 'published') {
                $project->forceFill(['built_core_version' => (string) config('app_factory.core_version', '1.0.0')])->save();
            }

            // Transizione tracciata (old→new) dello stato App Project.
            $project = $transition->execute($project, match ($status) {
                'published' => AppProjectStatus::Published,
                'built' => AppProjectStatus::Built,
                'failed' => AppProjectStatus::Failed,
                default => AppProjectStatus::Building,
            }, null, ['platform' => $platform, 'version' => $build->version]);

            return ['tenant' => $tenant->display_name, 'build' => $build, 'project' => $project];
        });

        if ($result === null) {
            $this->error("Tenant o App Project non trovato: {$uuid}");

            return self::FAILURE;
        }

        $this->info("Build registrata: {$result['tenant']} · {$platform} · {$status} (v{$result['build']->version})");
        $this->line("  stato app: {$result['project']->build_status->value}");

        return self::SUCCESS;
    }
}
