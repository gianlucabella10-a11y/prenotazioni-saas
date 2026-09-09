<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\AppFactory\Infrastructure\Models\AppVersion;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class AppVersionTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function actingSuperAdmin(): User
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function projectFor(Tenant $tenant, int $adminId): AppProject
    {
        return app(AllocateAppIdentifiers::class)->execute($tenant, 'barber_dark', $adminId);
    }

    public function test_super_admin_registers_a_version(): void
    {
        $admin = $this->actingSuperAdmin();
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant'], $admin->id);

        $this->post("/control-room/apps/{$project->uuid}/versioni", [
            'version' => '1.0.0',
            'build_number' => 3,
            'release_notes' => 'Prima beta',
        ])->assertRedirect();

        $version = $this->bypassTenancy(fn (): AppVersion => AppVersion::query()->firstOrFail());
        self::assertSame('1.0.0', $version->version);
        self::assertSame(3, $version->build_number);
        self::assertSame('active', $version->status);
    }

    public function test_duplicate_version_is_rejected(): void
    {
        $admin = $this->actingSuperAdmin();
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant'], $admin->id);

        $payload = ['version' => '1.0.0', 'build_number' => 1];
        $this->post("/control-room/apps/{$project->uuid}/versioni", $payload)->assertRedirect();
        $this->post("/control-room/apps/{$project->uuid}/versioni", $payload)->assertRedirect();

        self::assertSame(1, $this->bypassTenancy(fn (): int => AppVersion::query()->count()));
    }

    public function test_version_can_be_deprecated(): void
    {
        $admin = $this->actingSuperAdmin();
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant'], $admin->id);
        $this->post("/control-room/apps/{$project->uuid}/versioni", ['version' => '1.0.0', 'build_number' => 1]);
        $version = $this->bypassTenancy(fn (): AppVersion => AppVersion::query()->firstOrFail());

        $this->patch("/control-room/apps/{$project->uuid}/versioni/{$version->uuid}", ['status' => 'deprecated'])->assertRedirect();

        self::assertSame('deprecated', $this->bypassTenancy(fn (): string => AppVersion::query()->findOrFail($version->id)->status));
    }

    public function test_app_config_exposes_latest_active_release(): void
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant'], $admin->id);

        $this->bypassTenancy(function () use ($env, $project): void {
            AppVersion::query()->create(['tenant_id' => $env['tenant']->id, 'app_project_id' => $project->id, 'version' => '1.0.0', 'build_number' => 3, 'status' => 'active']);
            AppVersion::query()->create(['tenant_id' => $env['tenant']->id, 'app_project_id' => $project->id, 'version' => '1.1.0', 'build_number' => 7, 'status' => 'active']);
        });

        $this->getJson('/api/v1/app/config', $this->tenantKeyHeaders($env['tenant']))
            ->assertOk()
            ->assertJsonPath('release.build_number', 7)
            ->assertJsonPath('release.version', '1.1.0');
    }
}
