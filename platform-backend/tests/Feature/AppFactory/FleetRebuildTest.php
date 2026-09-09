<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Operazione di massa "Ricostruisci flotta stale" (FLEET_OPERATIONS.md):
 * accoda una build per ogni app stale con manifest già generato, senza CI/
 * terminale, riusando BuildFleet (selezione) + BuildService (accodamento).
 */
final class FleetRebuildTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function actingSuperAdmin(): User
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function staleProjectWithManifest(): AppProject
    {
        return $this->bypassTenancy(fn (): AppProject => AppProject::factory()->create([
            'build_status' => 'ready_to_build',
            'built_core_version' => null,
            'build_manifest' => ['app_identity' => ['package_name' => 'com.platform.test']],
        ]));
    }

    private function staleProjectWithoutManifest(): AppProject
    {
        return $this->bypassTenancy(fn (): AppProject => AppProject::factory()->create([
            'build_status' => 'ready_to_build',
            'built_core_version' => null,
            'build_manifest' => null,
        ]));
    }

    public function test_rebuild_queues_builds_for_stale_projects_with_manifest(): void
    {
        $this->actingSuperAdmin();
        $project = $this->staleProjectWithManifest();

        $this->post('/control-room/apps/flotta/ricostruisci', ['platform' => 'android'])
            ->assertRedirect()
            ->assertSessionHas('status', '1 build accodate.');

        // In test QUEUE_CONNECTION=sync (phpunit.xml): il job gira subito,
        // quindi lo stato finale dipende dal driver (default "manual" →
        // LogBuildDispatcher) e non è più "queued" al momento dell'assert —
        // qui verifichiamo solo che l'accodamento sia realmente avvenuto.
        $this->assertDatabaseHas('app_builds', [
            'app_project_id' => $project->id,
            'platform' => 'android',
        ]);
    }

    public function test_rebuild_skips_projects_without_manifest(): void
    {
        $this->actingSuperAdmin();
        $this->staleProjectWithoutManifest();

        $response = $this->post('/control-room/apps/flotta/ricostruisci', ['platform' => 'android']);
        $response->assertRedirect();

        $status = (string) session('status');
        self::assertStringContainsString('0 build accodate.', $status);
        self::assertStringContainsString('1 app saltate', $status);
    }

    public function test_rebuild_respects_limit(): void
    {
        $this->actingSuperAdmin();
        $this->staleProjectWithManifest();
        $this->staleProjectWithManifest();
        $this->staleProjectWithManifest();

        $this->post('/control-room/apps/flotta/ricostruisci', ['platform' => 'android', 'limit' => 1])
            ->assertSessionHas('status', '1 build accodate.');

        self::assertSame(1, $this->bypassTenancy(fn (): int => AppBuild::query()->count()));
    }

    public function test_rebuild_is_denied_to_guests(): void
    {
        $this->post('/control-room/apps/flotta/ricostruisci', ['platform' => 'android'])
            ->assertRedirect(route('control.login'));
    }
}
