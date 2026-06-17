<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Database\Factories\AppProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Distribuzione beta privata: link FIRMATO con scadenza che scarica l'APK reale
 * dalla cartella artifact per-tenant. Solo build `built`; senza firma è negato.
 */
final class BetaDistributionTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function builtBuild(Tenant $tenant, string $bytes = 'APKBYTES'): AppBuild
    {
        return $this->bypassTenancy(function () use ($tenant, $bytes): AppBuild {
            $project = AppProjectFactory::new()->create(['tenant_id' => $tenant->id]);
            $path = "builds/{$tenant->id}/1.0.0+1/app-release.apk";
            Storage::disk('local')->put($path, $bytes);

            return AppBuild::query()->create([
                'tenant_id' => $tenant->id,
                'app_project_id' => $project->id,
                'version' => '1.0.0+1',
                'platform' => 'android',
                'status' => 'built',
                'artifact_path' => $path,
                'checksum' => hash('sha256', $bytes),
            ]);
        });
    }

    public function test_signed_link_downloads_the_apk(): void
    {
        Storage::fake('local');
        $env = $this->provisionBookableTenant();
        $build = $this->builtBuild($env['tenant'], 'REAL-APK-A');

        $url = URL::temporarySignedRoute('beta.download', now()->addDay(), ['build' => $build->uuid]);

        $this->get($url)->assertOk();
    }

    public function test_unsigned_link_is_forbidden(): void
    {
        Storage::fake('local');
        $env = $this->provisionBookableTenant();
        $build = $this->builtBuild($env['tenant']);

        $this->get(route('beta.download', ['build' => $build->uuid]))->assertForbidden();
    }

    public function test_non_built_build_is_not_downloadable(): void
    {
        Storage::fake('local');
        $env = $this->provisionBookableTenant();
        $build = $this->builtBuild($env['tenant']);
        $this->bypassTenancy(fn () => $build->forceFill(['status' => 'building'])->save());

        $url = URL::temporarySignedRoute('beta.download', now()->addDay(), ['build' => $build->uuid]);

        $this->get($url)->assertNotFound();
    }

    public function test_artifacts_are_isolated_per_tenant(): void
    {
        Storage::fake('local');
        $a = $this->provisionBookableTenant();
        $b = $this->provisionBookableTenant();
        $buildA = $this->builtBuild($a['tenant'], 'APK-OF-A');
        $buildB = $this->builtBuild($b['tenant'], 'APK-OF-B');

        self::assertNotSame($buildA->artifact_path, $buildB->artifact_path);
        self::assertStringContainsString("builds/{$a['tenant']->id}/", (string) $buildA->artifact_path);

        $url = URL::temporarySignedRoute('beta.download', now()->addDay(), ['build' => $buildA->uuid]);
        $response = $this->get($url)->assertOk();
        self::assertSame('APK-OF-A', $response->streamedContent());
    }
}
