<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Foundation\Enums\UserType;
use App\Foundation\Tenancy\CurrentTenant;
use App\Models\User;
use App\Modules\AppFactory\Application\BuildService;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Avvia la build dell'app di un tenant da CLI (parità col bottone Control Room).
 * Accoda `RunAppBuildJob`; con `QUEUE_CONNECTION=sync` compila inline (one-shot
 * sulla build-machine). Nessuna firma/upload qui. Richiede un pacchetto generato.
 */
final class BuildAppCommand extends Command
{
    protected $signature = 'app:build {tenant : UUID del tenant} {platform=android : android|ios}';

    protected $description = 'Avvia la build dell\'app del tenant (accoda o esegue se queue=sync). Nessuna firma/upload.';

    public function handle(CurrentTenant $current, BuildService $builds): int
    {
        $uuid = (string) $this->argument('tenant');
        $platform = (string) $this->argument('platform');

        $project = $current->bypass(function () use ($uuid): ?AppProject {
            $tenant = Tenant::query()->where('uuid', $uuid)->first();

            return $tenant === null ? null : AppProject::query()->where('tenant_id', $tenant->id)->first();
        });

        if ($project === null) {
            $this->error("Tenant o App Project non trovato: {$uuid}");

            return self::FAILURE;
        }

        $actorId = $current->bypass(fn (): ?int => User::query()->where('type', UserType::SuperAdmin->value)->value('id'));

        try {
            $build = $builds->request($project, $platform, $actorId);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $fresh = $current->bypass(fn (): AppBuild => AppBuild::query()->findOrFail($build->id));

        $this->info("Build {$platform} richiesta: #{$fresh->id} · stato «{$fresh->status}»");
        $this->line("  artifact (a build riuscita): builds/{$project->tenant_id}/{$fresh->version}/app-release.apk");
        $this->line('  driver: '.config('app_factory.build_driver').' · queue: '.config('queue.default'));

        if (config('queue.default') !== 'sync') {
            $this->line('  → assicurati che il worker giri: php artisan queue:work');
        }

        return self::SUCCESS;
    }
}
