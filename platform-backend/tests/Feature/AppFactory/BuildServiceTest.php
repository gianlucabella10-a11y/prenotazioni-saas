<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Application\BuildService;
use App\Modules\AppFactory\Application\PrepareApp;
use App\Modules\AppFactory\Application\RunAppBuildJob;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class BuildServiceTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function generatedProject(Tenant $tenant): AppProject
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $project = app(AllocateAppIdentifiers::class)->execute($tenant, 'barber_dark', $admin->id);
        app(PrepareApp::class)->execute($project, $admin->id);

        return $this->bypassTenancy(fn (): AppProject => AppProject::query()->findOrFail($project->id));
    }

    public function test_request_enqueues_build_and_marks_project_queued(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $env = $this->provisionBookableTenant();
        $project = $this->generatedProject($env['tenant']);

        $build = app(BuildService::class)->request($project, 'android');

        self::assertSame('queued', $build->status);
        self::assertNotNull($build->queued_at);
        self::assertSame('queued', $this->bypassTenancy(fn (): string => AppProject::query()->findOrFail($project->id)->build_status->value));
        Queue::assertPushed(RunAppBuildJob::class);
    }

    public function test_request_rejects_project_without_package(): void
    {
        $env = $this->provisionBookableTenant();
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $project = app(AllocateAppIdentifiers::class)->execute($env['tenant'], 'barber_dark', $admin->id);

        $this->expectException(\RuntimeException::class);
        app(BuildService::class)->request($project, 'android'); // niente manifest → rifiutata
    }

    public function test_sync_pipeline_runs_worker_to_building(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $env = $this->provisionBookableTenant();
        $project = $this->generatedProject($env['tenant']);

        // Coda `sync` in test: il worker gira inline.
        $build = app(BuildService::class)->request($project, 'android');

        $fresh = $this->bypassTenancy(fn (): AppBuild => AppBuild::query()->findOrFail($build->id));
        self::assertSame('building', $fresh->status);
        self::assertNotNull($fresh->started_at);
        self::assertStringContainsString('manual://', (string) $fresh->artifact_path);
        self::assertSame('building', $this->bypassTenancy(fn (): string => AppProject::query()->findOrFail($project->id)->build_status->value));
    }
}
