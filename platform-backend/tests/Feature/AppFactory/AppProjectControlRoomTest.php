<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class AppProjectControlRoomTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function actingSuperAdmin(): User
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function projectFor(Tenant $tenant, int $adminId, string $template = 'default'): AppProject
    {
        return app(AllocateAppIdentifiers::class)->execute($tenant, $template, $adminId);
    }

    public function test_super_admin_lists_and_opens(): void
    {
        $admin = $this->actingSuperAdmin();
        $env = $this->provisionBookableTenant(['display_name' => 'Giuffrida Barber']);
        $project = $this->projectFor($env['tenant'], $admin->id, 'barber_dark');

        $this->get('/control-room/apps')->assertOk()->assertSee('Giuffrida Barber');
        $this->get("/control-room/apps/{$project->uuid}")->assertOk()->assertSee($project->bundle_id);
    }

    public function test_generate_creates_build_and_marks_generated(): void
    {
        Storage::fake('local');
        $admin = $this->actingSuperAdmin();
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant'], $admin->id);

        $this->post("/control-room/apps/{$project->uuid}/genera")->assertRedirect();

        self::assertSame(1, $this->bypassTenancy(fn () => AppBuild::query()->where('app_project_id', $project->id)->count()));
        self::assertSame('generated', $this->bypassTenancy(fn () => AppProject::query()->findOrFail($project->id)->build_status->value));
    }

    public function test_update_template_changes_project(): void
    {
        $admin = $this->actingSuperAdmin();
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant'], $admin->id, 'default');

        $this->put("/control-room/apps/{$project->uuid}/template", ['template_code' => 'beauty_visual'])->assertRedirect();

        self::assertSame('beauty_visual', $this->bypassTenancy(fn () => AppProject::query()->findOrFail($project->id)->template_code));
    }

    public function test_tenant_admin_is_forbidden(): void
    {
        $env = $this->provisionBookableTenant();
        $tenantAdmin = $this->createTenantAdmin($env['tenant']);

        $this->actingAs($tenantAdmin, 'admin')->get('/control-room/apps')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/control-room/apps')->assertRedirect(route('control.login'));
    }
}
