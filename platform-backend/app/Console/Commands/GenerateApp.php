<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Foundation\Enums\UserType;
use App\Foundation\Tenancy\CurrentTenant;
use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Application\PrepareApp;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\Branding\Infrastructure\Models\BrandAsset;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Prepara il pacchetto app di un tenant (asset + manifest 2.0). NON esegue
 * build native: porta l'App Project a `generated`/`ready_to_build`.
 */
final class GenerateApp extends Command
{
    protected $signature = 'app:generate {tenant : UUID del tenant}';

    protected $description = 'Prepara il pacchetto app del tenant (asset + manifest 2.0). Nessuna build nativa.';

    public function handle(CurrentTenant $current, AllocateAppIdentifiers $allocate, PrepareApp $prepare): int
    {
        $uuid = (string) $this->argument('tenant');

        $tenant = $current->bypass(fn (): ?Tenant => Tenant::query()->where('uuid', $uuid)->first());

        if ($tenant === null) {
            $this->error("Tenant non trovato: {$uuid}");

            return self::FAILURE;
        }

        $brand = $current->bypass(fn (): ?BrandProfile => BrandProfile::query()->where('tenant_id', $tenant->id)->first());

        if ($brand === null) {
            $this->error('Brand assente per questo tenant.');

            return self::FAILURE;
        }

        $actorId = $current->bypass(
            fn (): ?int => User::query()->where('type', UserType::SuperAdmin->value)->value('id')
        );

        // Idempotente: se l'App Project esiste già mantiene il suo template.
        $project = $allocate->execute($tenant, 'default', $actorId);

        $hasLogo = $current->bypass(fn (): bool => BrandAsset::query()
            ->where('brand_profile_id', $brand->id)
            ->whereIn('kind', [BrandAsset::KIND_LOGO, BrandAsset::KIND_ICON_SOURCE])
            ->exists());

        if (! $hasLogo) {
            $this->warn('Nessun logo: gli asset non verranno generati (stato resterà "generated").');
        }

        $build = $prepare->execute($project, $actorId);

        $fresh = $current->bypass(fn (): AppProject => AppProject::query()->findOrFail($project->id));

        $this->info("App preparata per «{$tenant->display_name}»");
        $this->line("  bundle id : {$project->bundle_id}");
        $this->line("  template  : {$project->template_code}");
        $this->line("  build     : {$build->version} ({$build->platform})");
        $this->line("  manifest  : {$build->artifact_path}");
        $this->line("  stato     : {$fresh->build_status->value}");

        return self::SUCCESS;
    }
}
