<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Application\Dispatchers\GithubBuildDispatcher;
use App\Modules\AppFactory\Application\Dispatchers\LogBuildDispatcher;
use App\Modules\AppFactory\Domain\AppProjectStatus;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;

/**
 * Avvia una build per-tenant dalla Control Room: seleziona il driver
 * (`app_factory.build_driver`), crea la riga `app_builds` (status=building) e
 * traccia la transizione dell'App Project a `building`. La compilazione vera
 * gira sul worker/CI; qui si orchestra e si traccia.
 */
final readonly class DispatchAppBuild
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private TransitionAppProject $transition,
        private Config $config,
        private Container $container,
    ) {}

    public function execute(AppProject $project, string $platform, ?int $actorUserId = null): AppBuild
    {
        return $this->currentTenant->bypass(function () use ($project, $platform, $actorUserId): AppBuild {
            $dispatcher = $this->driver();
            $reference = $dispatcher->dispatch($project, $platform);

            $next = AppBuild::query()->where('app_project_id', $project->id)->count() + 1;

            $build = AppBuild::query()->create([
                'tenant_id' => $project->tenant_id,
                'app_project_id' => $project->id,
                'version' => "1.0.0+{$next}",
                'platform' => $platform,
                'status' => 'building',
                'artifact_path' => $reference,
            ]);

            $this->transition->execute($project, AppProjectStatus::Building, $actorUserId, [
                'platform' => $platform,
                'driver' => $dispatcher->name(),
            ]);

            return $build;
        });
    }

    private function driver(): BuildDispatcher
    {
        return match ((string) $this->config->get('app_factory.build_driver', 'manual')) {
            'github' => $this->container->make(GithubBuildDispatcher::class),
            default => $this->container->make(LogBuildDispatcher::class),
        };
    }
}
