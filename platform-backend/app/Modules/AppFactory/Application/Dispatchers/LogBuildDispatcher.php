<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application\Dispatchers;

use App\Modules\AppFactory\Application\BuildDispatcher;
use App\Modules\AppFactory\Application\BuildDispatchResult;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use Illuminate\Support\Facades\Log;

/**
 * Driver di default **sicuro** (`manual`): non avvia alcuna build reale, ma
 * registra l'intento. L'operatore lancia la pipeline CI manualmente (workflow
 * `app-factory-build`). Nessun segreto richiesto: è il placeholder pronto
 * all'uso finché non si configura un driver reale (es. `github`).
 */
final class LogBuildDispatcher implements BuildDispatcher
{
    public function dispatch(AppProject $project, string $platform): BuildDispatchResult
    {
        Log::info('app_factory.build.dispatch', [
            'tenant_id' => $project->tenant_id,
            'app_project' => $project->uuid,
            'platform' => $platform,
        ]);

        return BuildDispatchResult::remote("manual://workflow-dispatch/{$project->uuid}/{$platform}");
    }

    public function name(): string
    {
        return 'manual';
    }
}
