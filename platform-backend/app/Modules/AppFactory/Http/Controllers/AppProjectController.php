<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Http\Controllers;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Application\AppPreview;
use App\Modules\AppFactory\Application\BuildFleet;
use App\Modules\AppFactory\Application\BuildService;
use App\Modules\AppFactory\Application\PrepareApp;
use App\Modules\AppFactory\Domain\TemplateRegistry;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\AppFactory\Infrastructure\Models\BetaFeedback;
use App\Modules\AppFactory\Infrastructure\Models\BetaTester;
use App\Modules\Branding\Application\RollbackBrandAssets;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * App Factory dalla Control Room: lista App Project, scheda, cambio template,
 * generazione pacchetto, download manifest. Solo super-admin (guard `admin`
 * + control.admin). Letture/scritture dei modelli tenant-scoped via bypass.
 */
final class AppProjectController extends Controller
{
    public function __construct(
        private readonly CurrentTenant $currentTenant,
        private readonly TemplateRegistry $templates,
    ) {}

    public function index(): View
    {
        [$projects, $tenants] = $this->currentTenant->bypass(function (): array {
            $projects = AppProject::query()->orderByDesc('id')->paginate(30);

            $tenants = Tenant::query()
                ->whereIn('id', collect($projects->items())->pluck('tenant_id'))
                ->get()
                ->keyBy('id');

            return [$projects, $tenants];
        });

        return view('control_room.apps.index', compact('projects', 'tenants'));
    }

    /** Osservabilità flotta (FASE 3): stato build aggregato + release train. */
    public function fleet(BuildFleet $fleet): View
    {
        return view('control_room.apps.fleet', $fleet->summary());
    }

    public function show(string $uuid, AppPreview $preview): View
    {
        $data = $this->currentTenant->bypass(function () use ($uuid): array {
            $project = AppProject::query()->where('uuid', $uuid)->firstOrFail();
            $tenant = Tenant::query()->findOrFail($project->tenant_id);
            $brand = BrandProfile::query()->where('tenant_id', $tenant->id)->first();
            $builds = AppBuild::query()->where('app_project_id', $project->id)->orderByDesc('id')->get();
            $feedback = BetaFeedback::query()->where('tenant_id', $tenant->id)->orderByDesc('id')->limit(10)->get();
            $testers = BetaTester::query()->where('tenant_id', $tenant->id)->orderByDesc('id')->get();

            return compact('project', 'tenant', 'brand', 'builds', 'feedback', 'testers');
        });

        $data['templates'] = $this->templates->all();
        $data['preview'] = $preview->execute($data['project']);
        $data['assetVersions'] = app(RollbackBrandAssets::class)->versions($data['tenant']->id);

        return view('control_room.apps.show', $data);
    }

    /** Accoda una build (FASE 1): crea app_builds(queued) + dispatch del worker. */
    public function dispatchBuild(Request $request, string $uuid, BuildService $build): RedirectResponse
    {
        $data = $request->validate(['platform' => ['required', 'in:android,ios']]);

        $project = $this->currentTenant->bypass(
            fn (): AppProject => AppProject::query()->where('uuid', $uuid)->firstOrFail()
        );

        try {
            $build->request($project, $data['platform'], $request->user('admin')->id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Build {$data['platform']} accodata: l'avanzamento è tracciato nella timeline.");
    }

    /** Invita un beta tester (roster per-tenant). */
    public function inviteTester(Request $request, string $uuid, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'device' => ['nullable', 'string', 'max:120'],
        ]);

        $tenantId = $this->currentTenant->bypass(
            fn (): int => AppProject::query()->where('uuid', $uuid)->firstOrFail()->tenant_id
        );

        $created = $this->currentTenant->bypass(function () use ($tenantId, $data): bool {
            if (BetaTester::query()->where('tenant_id', $tenantId)->where('email', $data['email'])->exists()) {
                return false;
            }

            BetaTester::query()->create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'email' => $data['email'],
                'device' => $data['device'] ?? null,
                'status' => 'invited',
            ]);

            return true;
        });

        if (! $created) {
            return back()->with('error', 'Tester già presente per questo cliente.');
        }

        $audit->log('beta_tester.invited', $request->user('admin')->id, ['email' => $data['email']], $tenantId);

        return back()->with('status', "Tester invitato: {$data['email']}.");
    }

    /** Cambia lo stato di un tester (invited/active/blocked). */
    public function updateTester(Request $request, string $uuid, string $tester, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', BetaTester::STATUSES)]]);

        $tenantId = $this->currentTenant->bypass(function () use ($uuid, $tester, $data): ?int {
            $project = AppProject::query()->where('uuid', $uuid)->firstOrFail();
            $row = BetaTester::query()->where('tenant_id', $project->tenant_id)->where('uuid', $tester)->first();

            if ($row === null) {
                return null;
            }

            $row->forceFill(['status' => $data['status']])->save();

            return $project->tenant_id;
        });

        if ($tenantId === null) {
            return back()->with('error', 'Tester non trovato.');
        }

        $audit->log('beta_tester.status_changed', $request->user('admin')->id, ['tester' => $tester, 'status' => $data['status']], $tenantId);

        return back()->with('status', 'Stato tester aggiornato.');
    }

    /** Genera un link beta privato (firmato, 7 giorni) per scaricare l'APK. */
    public function betaLink(Request $request, string $uuid, string $build, AuditLogger $audit): RedirectResponse
    {
        $row = $this->currentTenant->bypass(function () use ($uuid, $build): ?AppBuild {
            $project = AppProject::query()->where('uuid', $uuid)->firstOrFail();

            return AppBuild::query()
                ->where('app_project_id', $project->id)
                ->where('uuid', $build)
                ->where('status', 'built')
                ->first();
        });

        if ($row === null) {
            return back()->with('error', 'Build non distribuibile: serve lo stato «Compilata» con artifact.');
        }

        $url = URL::temporarySignedRoute('beta.download', now()->addDays(7), ['build' => $row->uuid]);
        $audit->log('app_build.beta_link', $request->user('admin')->id, ['build' => $row->uuid], $row->tenant_id);

        return back()->with('beta_link', $url);
    }

    /** Rollback dei derivati a una versione precedente (storico mai cancellato). */
    public function rollbackAssets(Request $request, string $uuid, RollbackBrandAssets $rollback): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        $tenantId = $this->currentTenant->bypass(
            fn (): int => AppProject::query()->where('uuid', $uuid)->firstOrFail()->tenant_id
        );

        $done = $rollback->execute($tenantId, (int) $data['version'], $request->user('admin')->id);

        return $done
            ? back()->with('status', "Asset riportati alla versione {$data['version']}. Rigenera il pacchetto per applicarli.")
            : back()->with('error', 'Versione asset non trovata.');
    }

    public function updateTemplate(Request $request, string $uuid, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'template_code' => ['required', 'string', 'in:'.implode(',', $this->templates->codes())],
        ]);

        $this->currentTenant->bypass(function () use ($uuid, $data, $request, $audit): void {
            $project = AppProject::query()->where('uuid', $uuid)->firstOrFail();

            $project->forceFill([
                'template_code' => $data['template_code'],
                'font_style' => $this->templates->fontStyle($data['template_code']),
            ])->save();

            // Bump del config_version del brand: l'app riprende il nuovo template.
            BrandProfile::query()->where('tenant_id', $project->tenant_id)->first()?->increment('config_version');

            $audit->log('app_project.template_changed', $request->user('admin')->id, ['template' => $data['template_code']], $project->tenant_id);
        });

        return back()->with('status', 'Template aggiornato: arriva all\'app alla prossima apertura.');
    }

    public function generate(Request $request, string $uuid, PrepareApp $prepare): RedirectResponse
    {
        $project = $this->currentTenant->bypass(
            fn (): AppProject => AppProject::query()->where('uuid', $uuid)->firstOrFail()
        );

        // Genera asset (dal logo) + manifest 2.0.
        $prepare->execute($project, $request->user('admin')->id);

        return back()->with('status', 'Pacchetto generato: asset e manifest pronti al download.');
    }

    public function download(string $uuid, string $build): StreamedResponse
    {
        $artifact = $this->currentTenant->bypass(function () use ($uuid, $build): ?string {
            $project = AppProject::query()->where('uuid', $uuid)->firstOrFail();

            return AppBuild::query()
                ->where('app_project_id', $project->id)
                ->where('uuid', $build)
                ->firstOrFail()
                ->artifact_path;
        });

        $disk = (string) config('app_factory.manifest_disk', 'local');

        abort_unless($artifact !== null && Storage::disk($disk)->exists($artifact), 404);

        return Storage::disk($disk)->download($artifact);
    }

    /** Scarica il pacchetto self-contained (generated_apps/{uuid}/) come ZIP. */
    public function downloadPackage(string $uuid): BinaryFileResponse
    {
        [$base, $diskName] = $this->currentTenant->bypass(function () use ($uuid): array {
            $project = AppProject::query()->where('uuid', $uuid)->firstOrFail();
            $tenant = Tenant::query()->findOrFail($project->tenant_id);

            $diskName = (string) config('app_factory.export_disk', config('app_factory.manifest_disk', 'local'));
            $base = trim((string) config('app_factory.export_base', 'generated_apps'), '/')."/{$tenant->uuid}";

            return [$base, $diskName];
        });

        $disk = Storage::disk($diskName);
        abort_unless($disk->exists("{$base}/manifest.json"), 404, 'Pacchetto non generato: genera prima il pacchetto.');

        $dir = $disk->path($base);
        $zipPath = (string) tempnam(sys_get_temp_dir(), 'app_pkg_').'.zip';

        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($dir) + 1));
            }
        }

        $zip->close();

        return response()->download($zipPath, "app-{$uuid}.zip", ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }
}
