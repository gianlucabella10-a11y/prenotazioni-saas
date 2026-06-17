<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Application\PrepareApp;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class BuildAppCommandTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function generatedProject(Tenant $tenant): AppProject
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $project = app(AllocateAppIdentifiers::class)->execute($tenant, 'barber_dark', $admin->id);
        app(PrepareApp::class)->execute($project, $admin->id);

        return $project;
    }

    public function test_command_starts_build_for_generated_project(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config(['app_factory.build_driver' => 'manual']);
        $env = $this->provisionBookableTenant();
        $this->generatedProject($env['tenant']);

        $this->artisan('app:build', ['tenant' => $env['tenant']->uuid, 'platform' => 'android'])->assertSuccessful();

        // Coda sync in test: il worker gira inline → build registrata.
        self::assertSame(1, $this->bypassTenancy(fn (): int => AppBuild::query()->where('platform', 'android')->count()));
    }

    public function test_command_fails_without_generated_package(): void
    {
        $env = $this->provisionBookableTenant();
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        app(AllocateAppIdentifiers::class)->execute($env['tenant'], 'barber_dark', $admin->id); // niente generate

        $this->artisan('app:build', ['tenant' => $env['tenant']->uuid, 'platform' => 'android'])->assertFailed();
    }

    public function test_command_fails_for_unknown_tenant(): void
    {
        $this->artisan('app:build', ['tenant' => 'no-such-uuid', 'platform' => 'android'])->assertFailed();
    }
}
