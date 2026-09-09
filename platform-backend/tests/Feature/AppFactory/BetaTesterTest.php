<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\AppFactory\Infrastructure\Models\BetaTester;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class BetaTesterTest extends TestCase
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

    public function test_super_admin_invites_tester(): void
    {
        $admin = $this->actingSuperAdmin();
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant'], $admin->id);

        $this->post("/control-room/apps/{$project->uuid}/testers", [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'device' => 'Pixel 7',
        ])->assertRedirect();

        $tester = $this->bypassTenancy(fn (): BetaTester => BetaTester::query()->firstOrFail());
        self::assertSame($env['tenant']->id, $tester->tenant_id);
        self::assertSame('invited', $tester->status);
        self::assertSame('mario@example.com', $tester->email);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $admin = $this->actingSuperAdmin();
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant'], $admin->id);

        $payload = ['name' => 'A', 'email' => 'dup@example.com'];
        $this->post("/control-room/apps/{$project->uuid}/testers", $payload)->assertRedirect();
        $this->post("/control-room/apps/{$project->uuid}/testers", $payload)->assertRedirect();

        self::assertSame(1, $this->bypassTenancy(fn (): int => BetaTester::query()->count()));
    }

    public function test_status_can_be_changed(): void
    {
        $admin = $this->actingSuperAdmin();
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant'], $admin->id);
        $this->post("/control-room/apps/{$project->uuid}/testers", ['name' => 'A', 'email' => 'a@example.com']);
        $tester = $this->bypassTenancy(fn (): BetaTester => BetaTester::query()->firstOrFail());

        $this->patch("/control-room/apps/{$project->uuid}/testers/{$tester->uuid}", ['status' => 'active'])->assertRedirect();

        self::assertSame('active', $this->bypassTenancy(fn (): string => BetaTester::query()->findOrFail($tester->id)->status));
    }

    public function test_invalid_status_is_rejected(): void
    {
        $admin = $this->actingSuperAdmin();
        $env = $this->provisionBookableTenant();
        $project = $this->projectFor($env['tenant'], $admin->id);
        $this->post("/control-room/apps/{$project->uuid}/testers", ['name' => 'A', 'email' => 'a@example.com']);
        $tester = $this->bypassTenancy(fn (): BetaTester => BetaTester::query()->firstOrFail());

        $this->patch("/control-room/apps/{$project->uuid}/testers/{$tester->uuid}", ['status' => 'nope'])
            ->assertSessionHasErrors('status');
    }

    public function test_tenant_admin_cannot_invite_testers(): void
    {
        $env = $this->provisionBookableTenant();
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $project = $this->projectFor($env['tenant'], $admin->id);
        $tenantAdmin = $this->createTenantAdmin($env['tenant']);

        $this->actingAs($tenantAdmin, 'admin')
            ->post("/control-room/apps/{$project->uuid}/testers", ['name' => 'A', 'email' => 'a@example.com'])
            ->assertForbidden();
    }
}
