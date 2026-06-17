<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Domain\AppProjectStatus;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;

/**
 * Punto UNICO di transizione dello stato di un App Project. Ogni cambio è
 * tracciato nell'audit trail con valore **vecchio → nuovo** (chi/quando/cosa).
 * No-op se lo stato è già quello richiesto (idempotente). L'identità resta
 * immutabile (guard sul model): qui cambia solo `build_status`.
 */
final readonly class TransitionAppProject
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $context dati extra per l'audit (es. platform) */
    public function execute(AppProject $project, AppProjectStatus $to, ?int $actorUserId = null, array $context = []): AppProject
    {
        return $this->currentTenant->bypass(function () use ($project, $to, $actorUserId, $context): AppProject {
            $fresh = AppProject::query()->findOrFail($project->id);
            $from = $fresh->build_status;

            if ($from === $to) {
                return $fresh; // idempotente: nessun rumore nell'audit
            }

            $fresh->forceFill(['build_status' => $to])->save();

            $this->audit->log(
                'app_project.status_changed',
                $actorUserId,
                ['from' => $from->value, 'to' => $to->value] + $context,
                $fresh->tenant_id,
                AppProject::class,
                $fresh->id,
            );

            return $fresh;
        });
    }

    /** Porta una bozza a "configured" quando brand/logo/template sono pronti. */
    public function markConfigured(AppProject $project, ?int $actorUserId = null): AppProject
    {
        return $project->build_status === AppProjectStatus::Draft
            ? $this->execute($project, AppProjectStatus::Configured, $actorUserId)
            : $project;
    }
}
