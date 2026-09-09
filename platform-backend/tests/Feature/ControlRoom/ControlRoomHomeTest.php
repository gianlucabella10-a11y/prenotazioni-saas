<?php

declare(strict_types=1);

namespace Tests\Feature\ControlRoom;

use App\Models\User;
use App\Modules\TenantManagement\Infrastructure\Models\Plan;
use App\Modules\TenantManagement\Infrastructure\Models\Subscription;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Cruscotto operativo Control Room (FOUNDER_EXPERIENCE_AUDIT.md): risponde a
 * "cosa devo fare adesso?" leggendo tabelle esistenti (jobs, failed_jobs,
 * app_builds, tenants) e il filesystem — nessuna scrittura, nessuna migration.
 */
final class ControlRoomHomeTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function actingSuperAdmin(): User
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_home_renders_with_no_data(): void
    {
        $this->actingSuperAdmin();

        // Nessun cliente/build/job: l'unica segnalazione attesa è "nessun
        // backup mai creato" (nessuno esiste davvero in questo ambiente di
        // test) — non "nessuna segnalazione", che sarebbe scorretto.
        $this->get('/control-room')
            ->assertOk()
            ->assertSee('Cosa devo fare adesso?')
            ->assertSee('Nessun cliente ancora.')
            ->assertSee('Nessuna build ancora.');
    }

    public function test_home_warns_when_no_backup_exists(): void
    {
        $this->actingSuperAdmin();

        $this->get('/control-room')
            ->assertOk()
            ->assertSee('Nessun backup è mai stato creato.');
    }

    public function test_home_shows_recent_tenant(): void
    {
        $this->actingSuperAdmin();
        $this->bypassTenancy(fn (): Tenant => Tenant::factory()->create(['display_name' => 'Cruscotto Demo']));

        $this->get('/control-room')
            ->assertOk()
            ->assertSee('Cruscotto Demo');
    }

    public function test_home_warns_about_expiring_and_expired_subscriptions(): void
    {
        $this->actingSuperAdmin();

        $this->bypassTenancy(function (): void {
            $plan = Plan::factory()->create();

            $tenantDueSoon = Tenant::factory()->create();
            Subscription::query()->create([
                'tenant_id' => $tenantDueSoon->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_end' => now()->addDays(3),
            ]);

            $tenantExpired = Tenant::factory()->create();
            Subscription::query()->create([
                'tenant_id' => $tenantExpired->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_end' => now()->subDay(),
            ]);
        });

        $this->get('/control-room')
            ->assertOk()
            ->assertSee('1 abbonamenti scaduti')
            ->assertSee('1 abbonamenti in scadenza nei prossimi 7 giorni');
    }
}
