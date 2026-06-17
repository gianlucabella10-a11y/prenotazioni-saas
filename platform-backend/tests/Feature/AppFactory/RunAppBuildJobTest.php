<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Application\PrepareApp;
use App\Modules\AppFactory\Application\RunAppBuildJob;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class RunAppBuildJobTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function queuedBuild(Tenant $tenant): AppBuild
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $project = app(AllocateAppIdentifiers::class)->execute($tenant, 'barber_dark', $admin->id);
        app(PrepareApp::class)->execute($project, $admin->id);

        return $this->bypassTenancy(fn (): AppBuild => AppBuild::query()->create([
            'tenant_id' => $tenant->id,
            'app_project_id' => $project->id,
            'version' => '1.0.0+1',
            'platform' => 'android',
            'status' => 'queued',
            'queued_at' => now(),
        ]));
    }

    public function test_worker_marks_failed_with_timeline_on_driver_error(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config([
            'app_factory.build_driver' => 'github',
            'app_factory.github.repo' => '',
            'app_factory.github.token' => '',
        ]);
        $env = $this->provisionBookableTenant();
        $build = $this->queuedBuild($env['tenant']);

        app()->call([new RunAppBuildJob($build->id), 'handle']);

        $fresh = $this->bypassTenancy(fn (): AppBuild => AppBuild::query()->findOrFail($build->id));
        self::assertSame('failed', $fresh->status);
        self::assertNotNull($fresh->started_at);
        self::assertNotNull($fresh->finished_at);
        self::assertStringContainsString('github', (string) $fresh->error_message);
        self::assertSame('failed', $this->bypassTenancy(fn (): string => AppProject::query()->findOrFail($fresh->app_project_id)->build_status->value));
    }

    public function test_worker_is_noop_for_missing_build(): void
    {
        app()->call([new RunAppBuildJob(999999), 'handle']);

        self::assertSame(0, $this->bypassTenancy(fn (): int => AppBuild::query()->count()));
    }
}
