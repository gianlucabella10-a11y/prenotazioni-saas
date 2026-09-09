<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Application\Dispatchers\GithubBuildDispatcher;
use App\Modules\AppFactory\Application\Dispatchers\LocalBuildDispatcher;
use App\Modules\AppFactory\Application\Dispatchers\LogBuildDispatcher;
use App\Modules\AppFactory\Domain\AppProjectStatus;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Worker della pipeline di build (FASE 1/2): porta la richiesta da `queued` a
 * `building`, seleziona il driver (`app_factory.build_driver`) e lancia la
 * compilazione esterna (manual = log / github = workflow_dispatch). L'esito
 * finale (built/published/failed) rientra via `app:build-record` dalla CI.
 * Aggiorna la timeline (started_at/finished_at) e, su errore, marca `failed`.
 */
final class RunAppBuildJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        private readonly int $buildId,
        private readonly ?int $actorUserId = null,
    ) {}

    public function handle(CurrentTenant $currentTenant, TransitionAppProject $transition, Config $config, Container $container): void
    {
        $currentTenant->bypass(function () use ($transition, $config, $container): void {
            $build = AppBuild::query()->find($this->buildId);

            if ($build === null) {
                return;
            }

            $project = AppProject::query()->find($build->app_project_id);

            if ($project === null) {
                return;
            }

            $build->forceFill(['status' => 'building', 'started_at' => now()])->save();
            $transition->execute($project, AppProjectStatus::Building, $this->actorUserId, ['platform' => $build->platform]);

            try {
                $driver = match ((string) $config->get('app_factory.build_driver', 'manual')) {
                    'github' => $container->make(GithubBuildDispatcher::class),
                    'local' => $container->make(LocalBuildDispatcher::class),
                    default => $container->make(LogBuildDispatcher::class),
                };

                $result = $driver->dispatch($project, (string) $build->platform);

                if ($result->completed) {
                    // Driver locale: build finita qui con artifact reale.
                    $build->forceFill([
                        'status' => 'built',
                        'artifact_path' => $result->artifactPath,
                        'checksum' => $result->checksum,
                        'size_bytes' => $result->sizeBytes,
                        'command' => $result->command,
                        'build_log' => $result->log,
                        'exit_code' => $result->exitCode,
                        'duration_ms' => $result->durationMs,
                        'finished_at' => now(),
                    ])->save();

                    $transition->execute($project, AppProjectStatus::Built, $this->actorUserId, [
                        'platform' => $build->platform,
                        'driver' => $driver->name(),
                    ]);
                } else {
                    // Driver remoto: l'esito finale rientra dalla CI con `app:build-record`.
                    $build->forceFill(['artifact_path' => $result->reference])->save();
                }

                Log::info('app_factory.build.dispatched', [
                    'tenant_id' => $project->tenant_id,
                    'app_project' => $project->uuid,
                    'platform' => $build->platform,
                    'driver' => $driver->name(),
                    'completed' => $result->completed,
                    'reference' => $result->reference,
                ]);
            } catch (Throwable $e) {
                $attributes = [
                    'status' => 'failed',
                    'finished_at' => now(),
                    'error_message' => $e->getMessage(),
                ];

                if ($e instanceof BuildFailedException) {
                    $attributes += [
                        'command' => $e->command,
                        'build_log' => $e->log,
                        'exit_code' => $e->exitCode,
                        'duration_ms' => $e->durationMs,
                    ];
                }

                $build->forceFill($attributes)->save();

                $transition->execute($project, AppProjectStatus::Failed, $this->actorUserId, ['platform' => $build->platform]);

                Log::error('app_factory.build.dispatch_failed', [
                    'app_project' => $project->uuid,
                    'platform' => $build->platform,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
