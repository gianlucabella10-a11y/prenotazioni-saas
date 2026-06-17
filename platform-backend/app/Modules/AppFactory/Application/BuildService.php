<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Domain\AppProjectStatus;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use RuntimeException;

/**
 * Punto d'ingresso della pipeline di build (FASE 1): valida il progetto,
 * crea la richiesta (`app_builds` status=queued) e la mette in CODA
 * (`RunAppBuildJob`). La compilazione vera gira sul worker/CI. Ogni cambio
 * stato è tracciato da TransitionAppProject (audit old→new).
 *
 *   AppProject → BuildService::request → app_builds(queued) → RunAppBuildJob
 */
final readonly class BuildService
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private TransitionAppProject $transition,
    ) {}

    public function request(AppProject $project, string $platform, ?int $actorUserId = null): AppBuild
    {
        if (! in_array($platform, ['android', 'ios'], true)) {
            throw new RuntimeException('Piattaforma non valida (android|ios).');
        }

        return $this->currentTenant->bypass(function () use ($project, $platform, $actorUserId): AppBuild {
            $fresh = AppProject::query()->findOrFail($project->id);

            // Validazione: serve un pacchetto generato (manifest) da costruire.
            if ($fresh->build_manifest === null) {
                throw new RuntimeException('Genera il pacchetto prima di avviare la build.');
            }

            $next = AppBuild::query()->where('app_project_id', $fresh->id)->count() + 1;

            $build = AppBuild::query()->create([
                'tenant_id' => $fresh->tenant_id,
                'app_project_id' => $fresh->id,
                'version' => "1.0.0+{$next}",
                'platform' => $platform,
                'status' => 'queued',
                'queued_at' => now(),
                'created_by' => $actorUserId,
            ]);

            $this->transition->execute($fresh, AppProjectStatus::Queued, $actorUserId, ['platform' => $platform]);

            RunAppBuildJob::dispatch($build->id, $actorUserId);

            return $build;
        });
    }
}
