<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application;

use App\Modules\AppFactory\Infrastructure\Models\AppProject;

/**
 * Contratto del motore di build: avvia la compilazione per-tenant su un worker
 * esterno (la compilazione nativa NON gira nell'app Laravel). Le implementazioni
 * sono selezionate via `app_factory.build_driver`. Ritorna un riferimento al job
 * (URL/descrizione) tracciato in `app_builds.artifact_path`.
 */
interface BuildDispatcher
{
    public function dispatch(AppProject $project, string $platform): string;

    /** Identificativo del driver (per audit/log). */
    public function name(): string;
}
