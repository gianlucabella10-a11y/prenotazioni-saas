<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Domain\AppProjectStatus;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\AppFactory\Infrastructure\Models\BetaTester;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * App Factory (FASE 3 — scala): interroga la "flotta" di App Project per la
 * build a lotti. Fornisce (a) la *matrice* di UUID da costruire — consumata
 * dalla CI matrix — e (b) un *riepilogo* per l'osservabilità in Control Room.
 * Cross-tenant: legge tutti i tenant via `CurrentTenant::bypass` (contesto
 * super-admin / CLI). Nessuna build qui.
 */
final readonly class BuildFleet
{
    /** Stati che hanno un pacchetto e possono essere (ri)costruiti. */
    private const BUILDABLE = [
        'ready_to_build', // AppProjectStatus::ReadyToBuild
        'published',      // AppProjectStatus::Published
        'failed',         // AppProjectStatus::Failed
    ];

    public function __construct(
        private CurrentTenant $currentTenant,
        private Config $config,
    ) {}

    public function coreVersion(): string
    {
        return (string) $this->config->get('app_factory.core_version', '1.0.0');
    }

    /**
     * UUID dei tenant da costruire. `staleOnly`: solo quelli mai costruiti o
     * con un core precedente (release train). `limit`: per il canary.
     *
     * @return list<string>
     */
    public function matrix(bool $staleOnly = false, ?int $limit = null): array
    {
        return $this->currentTenant->bypass(function () use ($staleOnly, $limit): array {
            $query = AppProject::query()->whereIn('build_status', self::BUILDABLE);

            if ($staleOnly) {
                $this->applyStale($query);
            }

            $query->orderBy('id');

            if ($limit !== null && $limit > 0) {
                $query->limit($limit);
            }

            return $query->pluck('uuid')->all();
        });
    }

    /**
     * Riepilogo per la dashboard: conteggi per stato, versione core corrente,
     * quante app sono stale / allineate, e le ultime build.
     *
     * @return array{by_status: array<string,int>, current_core: string, stale: int, on_current: int, buildable: int, recent: Collection<int,AppBuild>, tenants: Collection<int,Tenant>}
     */
    public function summary(): array
    {
        return $this->currentTenant->bypass(function (): array {
            $core = $this->coreVersion();

            $byStatus = AppProject::query()
                ->selectRaw('build_status, count(*) as c')
                ->groupBy('build_status')
                ->get()
                ->mapWithKeys(fn (AppProject $r): array => [$r->build_status->value => (int) $r->c])
                ->all();

            $stale = $this->applyStale(AppProject::query()->whereIn('build_status', self::BUILDABLE))->count();

            $onCurrent = AppProject::query()
                ->where('build_status', AppProjectStatus::Published->value)
                ->where('built_core_version', $core)
                ->count();

            $buildable = AppProject::query()->whereIn('build_status', self::BUILDABLE)->count();

            $recent = AppBuild::query()->where('platform', '!=', 'config')->orderByDesc('id')->limit(15)->get();
            $tenants = Tenant::query()->whereIn('id', $recent->pluck('tenant_id')->unique()->all())->get()->keyBy('id');

            // Contatori build native (escluso il record 'config' di generazione).
            $buildRows = AppBuild::query()->where('platform', '!=', 'config');
            $builds = [
                'succeeded' => (clone $buildRows)->whereIn('status', ['built', 'published'])->count(),
                'failed' => (clone $buildRows)->where('status', 'failed')->count(),
                'in_progress' => (clone $buildRows)->whereIn('status', ['queued', 'building'])->count(),
            ];

            $betaActive = BetaTester::query()->where('status', 'active')->count();

            return [
                'by_status' => $byStatus,
                'current_core' => $core,
                'stale' => $stale,
                'on_current' => $onCurrent,
                'buildable' => $buildable,
                'builds' => $builds,
                'beta_active' => $betaActive,
                'recent' => $recent,
                'tenants' => $tenants,
            ];
        });
    }

    /** Filtra a "stale": mai costruito (null) o costruito con un core diverso. */
    private function applyStale(Builder $query): Builder
    {
        $core = $this->coreVersion();

        return $query->where(function (Builder $w) use ($core): void {
            $w->whereNull('built_core_version')->orWhere('built_core_version', '!=', $core);
        });
    }
}
