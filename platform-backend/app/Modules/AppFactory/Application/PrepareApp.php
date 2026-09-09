<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application;

use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\Branding\Application\GenerateBrandAssets;

/**
 * Orchestratore della preparazione app: genera gli asset dal logo (FASE 2A),
 * poi il manifest 2.0, poi assembla il pacchetto self-contained scaricabile
 * (FASE 2C). Punto unico condiviso da Control Room e comando `app:generate`
 * (nessuna duplicazione).
 */
final readonly class PrepareApp
{
    public function __construct(
        private GenerateBrandAssets $assets,
        private GenerateAppPackage $package,
        private ExportAppPackage $export,
    ) {}

    public function execute(AppProject $project, ?int $actorUserId): AppBuild
    {
        $this->assets->execute($project->tenant_id);
        $build = $this->package->execute($project, $actorUserId);
        $this->export->execute($project);

        return $build;
    }
}
