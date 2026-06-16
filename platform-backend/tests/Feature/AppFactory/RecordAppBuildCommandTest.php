<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class RecordAppBuildCommandTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function projectFor(int $tenantId): AppProject
    {
        return $this->bypassTenancy(fn (): AppProject => AppProject::factory()->create(['tenant_id' => $tenantId]));
    }

    public function test_records_published_build_and_marks_project_published(): void
    {
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant']->id);

        $this->artisan('app:build-record', [
            'tenant' => $env['tenant']->uuid,
            'platform' => 'android',
            'status' => 'published',
            '--app-version' => '1.0.0+5',
            '--artifact' => 'play://internal/com.platform.t1a',
        ])->assertSuccessful();

        $build = $this->bypassTenancy(fn (): AppBuild => AppBuild::query()->where('app_project_id', $project->id)->firstOrFail());
        self::assertSame('android', $build->platform);
        self::assertSame('published', $build->status);
        self::assertSame('1.0.0+5', $build->version);
        self::assertSame('play://internal/com.platform.t1a', $build->artifact_path);
        self::assertSame('published', $this->bypassTenancy(fn (): string => $project->fresh()->build_status->value));
    }

    public function test_failed_build_marks_project_failed(): void
    {
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant']->id);

        $this->artisan('app:build-record', [
            'tenant' => $env['tenant']->uuid,
            'platform' => 'ios',
            'status' => 'failed',
        ])->assertSuccessful();

        self::assertSame('failed', $this->bypassTenancy(fn (): string => $project->fresh()->build_status->value));
    }

    public function test_built_status_marks_project_building(): void
    {
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant']->id);

        $this->artisan('app:build-record', [
            'tenant' => $env['tenant']->uuid,
            'platform' => 'android',
            'status' => 'built',
        ])->assertSuccessful();

        self::assertSame('building', $this->bypassTenancy(fn (): string => $project->fresh()->build_status->value));
    }

    public function test_version_defaults_when_omitted(): void
    {
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant']->id);

        $this->artisan('app:build-record', [
            'tenant' => $env['tenant']->uuid,
            'platform' => 'android',
            'status' => 'building',
        ])->assertSuccessful();

        $build = $this->bypassTenancy(fn (): AppBuild => AppBuild::query()->where('app_project_id', $project->id)->firstOrFail());
        self::assertSame('1.0.0+1', $build->version);
    }

    public function test_rejects_invalid_platform(): void
    {
        $env = $this->provisionBookableTenant();
        $this->projectFor($env['tenant']->id);

        $this->artisan('app:build-record', [
            'tenant' => $env['tenant']->uuid,
            'platform' => 'web',
            'status' => 'built',
        ])->assertFailed();
    }

    public function test_rejects_invalid_status(): void
    {
        $env = $this->provisionBookableTenant();
        $this->projectFor($env['tenant']->id);

        $this->artisan('app:build-record', [
            'tenant' => $env['tenant']->uuid,
            'platform' => 'android',
            'status' => 'uploaded',
        ])->assertFailed();
    }

    public function test_fails_for_unknown_tenant(): void
    {
        $this->artisan('app:build-record', [
            'tenant' => 'no-such-uuid',
            'platform' => 'android',
            'status' => 'built',
        ])->assertFailed();
    }

    public function test_fails_when_app_project_missing(): void
    {
        $env = $this->provisionBookableTenant(); // tenant senza App Project

        $this->artisan('app:build-record', [
            'tenant' => $env['tenant']->uuid,
            'platform' => 'android',
            'status' => 'built',
        ])->assertFailed();
    }
}
