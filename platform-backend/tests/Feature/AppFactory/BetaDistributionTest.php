<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\AppFactory\Infrastructure\Models\BetaDownloadToken;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Distribuzione beta: link con TOKEN opaco (scadenza, limite, conteggio,
 * revoca). Scarica l'APK reale dalla cartella artifact per-tenant; solo build
 * `built`. Nessun APK esposto direttamente.
 */
final class BetaDistributionTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function builtBuild(Tenant $tenant, string $bytes = 'APKBYTES'): AppBuild
    {
        return $this->bypassTenancy(function () use ($tenant, $bytes): AppBuild {
            $project = AppProject::factory()->create(['tenant_id' => $tenant->id]);
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

    /** @param array<string, mixed> $overrides */
    private function token(AppBuild $build, array $overrides = []): BetaDownloadToken
    {
        return $this->bypassTenancy(fn (): BetaDownloadToken => BetaDownloadToken::query()->create([
            'token' => $overrides['token'] ?? Str::random(48),
            'tenant_id' => $build->tenant_id,
            'app_build_id' => $build->id,
            'expires_at' => $overrides['expires_at'] ?? now()->addDay(),
            'max_downloads' => $overrides['max_downloads'] ?? null,
            'download_count' => $overrides['download_count'] ?? 0,
            'revoked_at' => $overrides['revoked_at'] ?? null,
        ]));
    }

    public function test_valid_token_downloads_and_increments_count(): void
    {
        Storage::fake('local');
        $env = $this->provisionBookableTenant();
        $token = $this->token($this->builtBuild($env['tenant'], 'REAL-APK'));

        $this->get(route('beta.download', ['token' => $token->token]))->assertOk();

        self::assertSame(1, $this->bypassTenancy(fn (): int => BetaDownloadToken::query()->findOrFail($token->id)->download_count));
    }

    public function test_revoked_token_is_denied(): void
    {
        Storage::fake('local');
        $env = $this->provisionBookableTenant();
        $token = $this->token($this->builtBuild($env['tenant']), ['revoked_at' => now()]);

        $this->get(route('beta.download', ['token' => $token->token]))->assertNotFound();
    }

    public function test_expired_token_is_denied(): void
    {
        Storage::fake('local');
        $env = $this->provisionBookableTenant();
        $token = $this->token($this->builtBuild($env['tenant']), ['expires_at' => now()->subHour()]);

        $this->get(route('beta.download', ['token' => $token->token]))->assertNotFound();
    }

    public function test_exhausted_token_is_denied(): void
    {
        Storage::fake('local');
        $env = $this->provisionBookableTenant();
        $token = $this->token($this->builtBuild($env['tenant']), ['max_downloads' => 1, 'download_count' => 1]);

        $this->get(route('beta.download', ['token' => $token->token]))->assertNotFound();
    }

    public function test_unknown_token_is_denied(): void
    {
        $this->get(route('beta.download', ['token' => 'does-not-exist']))->assertNotFound();
    }

    public function test_download_serves_the_correct_tenant_artifact(): void
    {
        Storage::fake('local');
        $a = $this->provisionBookableTenant();
        $b = $this->provisionBookableTenant();
        $tokenA = $this->token($this->builtBuild($a['tenant'], 'APK-OF-A'));
        $this->token($this->builtBuild($b['tenant'], 'APK-OF-B'));

        $response = $this->get(route('beta.download', ['token' => $tokenA->token]))->assertOk();
        self::assertSame('APK-OF-A', $response->streamedContent());
    }

    public function test_super_admin_generates_then_revokes_link(): void
    {
        Storage::fake('local');
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $this->actingAs($admin, 'admin');
        $env = $this->provisionBookableTenant();
        $build = $this->builtBuild($env['tenant']);
        $project = $this->bypassTenancy(fn (): AppProject => AppProject::query()->findOrFail($build->app_project_id));

        $this->post("/control-room/apps/{$project->uuid}/beta-link/{$build->uuid}")->assertRedirect();
        $token = $this->bypassTenancy(fn (): BetaDownloadToken => BetaDownloadToken::query()->firstOrFail());
        $this->get(route('beta.download', ['token' => $token->token]))->assertOk();

        $this->post("/control-room/apps/{$project->uuid}/beta-link/{$token->id}/revoca")->assertRedirect();

        $this->get(route('beta.download', ['token' => $token->token]))->assertNotFound();
    }
}
