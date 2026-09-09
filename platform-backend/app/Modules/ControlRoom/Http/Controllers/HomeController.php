<?php

declare(strict_types=1);

namespace App\Modules\ControlRoom\Http\Controllers;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\TenantManagement\Infrastructure\Models\Subscription;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Cruscotto operativo della Control Room ("cosa devo fare adesso?" — non solo
 * dati). Legge esclusivamente tabelle già esistenti (jobs, failed_jobs,
 * app_builds, tenants, subscriptions) e il filesystem (backups/, disco
 * storage) — nessuna migration nuova, nessun nuovo stato persistito
 * (FOUNDER_AUTOMATION_MATRIX.md). Il segnale sui rinnovi (BUSINESS_OS_AUDIT.md
 * — area "Rinnovi", oggi MISSING come processo) usa `current_period_end`,
 * colonna già popolata da ProvisionTenant ma finora mai osservata da nessuna
 * schermata: rende visibile un dato che esisteva già, non introduce
 * fatturazione o rinnovo automatico.
 */
final class HomeController extends Controller
{
    private const int STUCK_JOB_MINUTES = 10;

    private const int BACKUP_STALE_DAYS = 2;

    private const int RENEWAL_DUE_SOON_DAYS = 7;

    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function index(): View
    {
        $data = $this->currentTenant->bypass(function (): array {
            $tenantCounts = Tenant::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $recentTenants = Tenant::query()->orderByDesc('id')->limit(5)->get();

            $recentBuilds = AppBuild::query()
                ->with('appProject')
                ->orderByDesc('id')
                ->limit(8)
                ->get();

            $tenantNames = Tenant::query()
                ->whereIn('id', $recentBuilds->pluck('appProject.tenant_id')->filter()->unique())
                ->pluck('display_name', 'id');

            $failedBuilds24h = AppBuild::query()
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count();

            return compact('tenantCounts', 'recentTenants', 'recentBuilds', 'tenantNames', 'failedBuilds24h');
        });

        $queue = $this->queueHealth();
        $backup = $this->latestBackup();
        $disk = $this->diskHealth();
        $renewals = $this->renewalHealth();

        $alerts = $this->buildAlerts($data['failedBuilds24h'], $queue, $backup, $disk, $renewals);

        return view('control_room.home.index', [
            ...$data,
            'queue' => $queue,
            'backup' => $backup,
            'disk' => $disk,
            'renewals' => $renewals,
            'alerts' => $alerts,
        ]);
    }

    /** @return array{dueSoon: int, expired: int} */
    private function renewalHealth(): array
    {
        return $this->currentTenant->bypass(function (): array {
            $active = Subscription::query()->whereIn('status', ['trialing', 'active', 'past_due']);

            return [
                'dueSoon' => (clone $active)->whereBetween('current_period_end', [now(), now()->addDays(self::RENEWAL_DUE_SOON_DAYS)])->count(),
                'expired' => (clone $active)->where('current_period_end', '<', now())->count(),
            ];
        });
    }

    /** @return array{pending: int, failed: int, oldestPendingMinutes: ?int} */
    private function queueHealth(): array
    {
        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();
        $oldest = DB::table('jobs')->min('created_at');

        return [
            'pending' => $pending,
            'failed' => $failed,
            'oldestPendingMinutes' => $oldest === null ? null : (int) round((time() - (int) $oldest) / 60),
        ];
    }

    /** @return array{filename: ?string, createdAt: ?int, daysAgo: ?float} */
    private function latestBackup(): array
    {
        $files = glob(base_path('../backups/backup-*.zip')) ?: [];

        if ($files === []) {
            return ['filename' => null, 'createdAt' => null, 'daysAgo' => null];
        }

        $latest = null;
        $latestTime = 0;

        foreach ($files as $file) {
            $mtime = filemtime($file) ?: 0;

            if ($mtime > $latestTime) {
                $latestTime = $mtime;
                $latest = $file;
            }
        }

        return [
            'filename' => $latest === null ? null : basename($latest),
            'createdAt' => $latestTime,
            'daysAgo' => round((time() - $latestTime) / 86400, 1),
        ];
    }

    /** @return array{freeBytes: int, totalBytes: int, freePercent: float} */
    private function diskHealth(): array
    {
        $path = storage_path('app');
        $free = disk_free_space($path) ?: 0;
        $total = disk_total_space($path) ?: 1;

        return [
            'freeBytes' => (int) $free,
            'totalBytes' => (int) $total,
            'freePercent' => round(($free / $total) * 100, 1),
        ];
    }

    /**
     * @param  array{pending: int, failed: int, oldestPendingMinutes: ?int}  $queue
     * @param  array{filename: ?string, createdAt: ?int, daysAgo: ?float}  $backup
     * @param  array{freeBytes: int, totalBytes: int, freePercent: float}  $disk
     * @param  array{dueSoon: int, expired: int}  $renewals
     * @return list<array{level: string, message: string, actionLabel: ?string, actionRoute: ?string}>
     */
    private function buildAlerts(int $failedBuilds24h, array $queue, array $backup, array $disk, array $renewals): array
    {
        $alerts = [];

        if ($backup['filename'] === null) {
            $alerts[] = ['level' => 'warn', 'message' => 'Nessun backup è mai stato creato.', 'actionLabel' => 'Crea backup ora', 'actionRoute' => 'control.backup.index'];
        } elseif ($backup['daysAgo'] >= self::BACKUP_STALE_DAYS) {
            $alerts[] = ['level' => 'warn', 'message' => "Ultimo backup: {$backup['daysAgo']} giorni fa.", 'actionLabel' => 'Crea backup ora', 'actionRoute' => 'control.backup.index'];
        }

        if ($queue['failed'] > 0) {
            $alerts[] = ['level' => 'danger', 'message' => "{$queue['failed']} job in coda sono falliti.", 'actionLabel' => null, 'actionRoute' => null];
        }

        if ($queue['oldestPendingMinutes'] !== null && $queue['oldestPendingMinutes'] >= self::STUCK_JOB_MINUTES) {
            $alerts[] = ['level' => 'danger', 'message' => "Un job è in coda da {$queue['oldestPendingMinutes']} minuti — il worker potrebbe non essere attivo.", 'actionLabel' => null, 'actionRoute' => null];
        }

        if ($failedBuilds24h > 0) {
            $alerts[] = ['level' => 'warn', 'message' => "{$failedBuilds24h} build fallite nelle ultime 24 ore.", 'actionLabel' => null, 'actionRoute' => null];
        }

        if ($disk['freePercent'] < 10.0) {
            $alerts[] = ['level' => 'danger', 'message' => "Spazio disco basso: {$disk['freePercent']}% libero.", 'actionLabel' => null, 'actionRoute' => null];
        }

        if ($renewals['expired'] > 0) {
            $alerts[] = ['level' => 'danger', 'message' => "{$renewals['expired']} abbonamenti scaduti — nessun rinnovo automatico esiste, verifica manualmente.", 'actionLabel' => null, 'actionRoute' => null];
        }

        if ($renewals['dueSoon'] > 0) {
            $alerts[] = ['level' => 'warn', 'message' => "{$renewals['dueSoon']} abbonamenti in scadenza nei prossimi ".self::RENEWAL_DUE_SOON_DAYS.' giorni.', 'actionLabel' => null, 'actionRoute' => null];
        }

        return $alerts;
    }
}
