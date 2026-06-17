<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application\Dispatchers;

use App\Modules\AppFactory\Application\BuildDispatcher;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Driver `github`: avvia la build per-tenant via GitHub Actions
 * `workflow_dispatch` sul workflow `app-factory-build`. Tutti i segreti
 * arrivano dalla config/ENV (mai nel codice). Interfaccia pronta: se il driver
 * non è configurato fallisce con un messaggio chiaro (nessuna azione silenziosa).
 */
final class GithubBuildDispatcher implements BuildDispatcher
{
    public function __construct(private readonly Config $config) {}

    public function dispatch(AppProject $project, string $platform): string
    {
        $repo = (string) $this->config->get('app_factory.github.repo', '');
        $token = (string) $this->config->get('app_factory.github.token', '');
        $workflow = (string) $this->config->get('app_factory.github.workflow', 'app-factory-build.yml');
        $ref = (string) $this->config->get('app_factory.github.ref', 'main');

        if ($repo === '' || $token === '') {
            throw new RuntimeException('Driver build "github" non configurato: imposta APP_FACTORY_GITHUB_REPO e APP_FACTORY_GITHUB_TOKEN (vedi APP_FACTORY_RELEASE_SECRETS.md).');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post("https://api.github.com/repos/{$repo}/actions/workflows/{$workflow}/dispatches", [
                'ref' => $ref,
                'inputs' => [
                    'tenant_uuid' => $project->uuid,
                    'platform' => $platform,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Dispatch GitHub fallito ({$response->status()}): ".$response->body());
        }

        return "github://{$repo}/actions/workflows/{$workflow}";
    }

    public function name(): string
    {
        return 'github';
    }
}
