<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Application\DispatchAppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class DispatchAppBuildTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function project(): AppProject
    {
        $env = $this->provisionBookableTenant();
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());

        return app(AllocateAppIdentifiers::class)->execute($env['tenant'], 'barber_dark', $admin->id);
    }

    public function test_manual_driver_creates_building_record_and_transitions_project(): void
    {
        config(['app_factory.build_driver' => 'manual']);
        $project = $this->project();

        $build = app(DispatchAppBuild::class)->execute($project, 'android');

        self::assertSame('building', $build->status);
        self::assertSame('android', $build->platform);
        self::assertStringContainsString('manual://', (string) $build->artifact_path);
        self::assertSame('building', $this->bypassTenancy(fn (): string => AppProject::query()->findOrFail($project->id)->build_status->value));
    }

    public function test_github_driver_unconfigured_fails_without_side_effects(): void
    {
        config([
            'app_factory.build_driver' => 'github',
            'app_factory.github.repo' => '',
            'app_factory.github.token' => '',
        ]);
        $project = $this->project();

        try {
            app(DispatchAppBuild::class)->execute($project, 'android');
            self::fail('Atteso RuntimeException per driver github non configurato.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('github', $e->getMessage());
        }

        // Nessun side-effect: niente riga build, stato del progetto invariato.
        self::assertSame(0, $this->bypassTenancy(fn (): int => AppBuild::query()->where('app_project_id', $project->id)->count()));
        self::assertNotSame('building', $this->bypassTenancy(fn (): string => AppProject::query()->findOrFail($project->id)->build_status->value));
    }
}
