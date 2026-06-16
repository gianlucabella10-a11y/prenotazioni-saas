<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Domain\AppProjectStatus;
use App\Modules\AppFactory\Domain\TemplateRegistry;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;

/**
 * Alloca l'identità store/build di un tenant (App Project): bundle_id /
 * package_name / slug / shortcode UNICI e immutabili, secondo la convenzione
 * `com.<prefix>.t<shortcode>` (docs/27 §3). Idempotente: un tenant ha un solo
 * App Project. Gira platform-level (bypass) — invocato dalla Control Room.
 */
final readonly class AllocateAppIdentifiers
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private Config $config,
        private AuditLogger $audit,
        private TemplateRegistry $templates,
    ) {}

    public function execute(Tenant $tenant, string $templateCode, ?int $actorUserId): AppProject
    {
        return $this->currentTenant->bypass(function () use ($tenant, $templateCode, $actorUserId): AppProject {
            $existing = AppProject::query()->where('tenant_id', $tenant->id)->first();

            if ($existing !== null) {
                return $existing; // idempotente
            }

            $template = $this->resolveTemplate($templateCode);
            $shortcode = $this->shortcodeFor($tenant);
            $prefix = rtrim((string) $this->config->get('app_factory.bundle_prefix', 'com.platform'), '.');
            $bundleId = "{$prefix}.t{$shortcode}";

            $project = AppProject::query()->create([
                'tenant_id' => $tenant->id,
                'slug' => Str::slug($tenant->display_name)."-{$shortcode}",
                'shortcode' => $shortcode,
                'store_name' => Str::limit($tenant->display_name, 30, ''),
                'bundle_id' => $bundleId,
                'package_name' => $bundleId,
                'template_code' => $template['code'],
                'font_style' => $template['font_style'],
                'powered_by_enabled' => true,
                'build_status' => AppProjectStatus::Draft,
            ]);

            $this->audit->log(
                'app_project.allocated',
                $actorUserId,
                ['template' => $template['code'], 'bundle_id' => $bundleId],
                $tenant->id,
                AppProject::class,
                $project->id,
            );

            return $project;
        });
    }

    /** @return array{code: string, font_style: ?string} */
    private function resolveTemplate(string $code): array
    {
        $resolved = $this->templates->has($code) ? $code : TemplateRegistry::DEFAULT_CODE;

        return [
            'code' => $resolved,
            'font_style' => $this->templates->fontStyle($resolved),
        ];
    }

    /** Shortcode deterministico e unico dal tenant id (base36, minuscolo). */
    private function shortcodeFor(Tenant $tenant): string
    {
        return base_convert((string) $tenant->id, 10, 36);
    }
}
