<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use RuntimeException;

/**
 * Operazione di massa (FLEET_OPERATIONS.md): accoda una build per ogni app
 * "stale" della flotta, riusando BuildFleet (selezione) + BuildService
 * (accodamento) — nessuna logica di build nuova, solo orchestrazione a lotti
 * di ciò che oggi si faceva un progetto alla volta da Control Room, o da
 * `app:build-matrix` + CI. Sostituisce, per l'uso quotidiano del Founder, la
 * necessità di aprire un terminale per lanciare la CI matrix manualmente.
 */
final readonly class QueueFleetRebuild
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private BuildFleet $fleet,
        private BuildService $buildService,
    ) {}

    /** @return array{queued: int, skipped: int, skippedReasons: list<string>} */
    public function execute(string $platform, ?int $limit, ?int $actorUserId): array
    {
        $uuids = $this->fleet->matrix(staleOnly: true, limit: $limit);

        $queued = 0;
        $skipped = 0;
        $skippedReasons = [];

        foreach ($uuids as $uuid) {
            $project = $this->currentTenant->bypass(
                fn (): ?AppProject => AppProject::query()->where('uuid', $uuid)->first()
            );

            if ($project === null) {
                continue;
            }

            try {
                $this->buildService->request($project, $platform, $actorUserId);
                $queued++;
            } catch (RuntimeException $e) {
                $skipped++;
                $skippedReasons[] = "{$project->store_name}: {$e->getMessage()}";
            }
        }

        return ['queued' => $queued, 'skipped' => $skipped, 'skippedReasons' => $skippedReasons];
    }
}
