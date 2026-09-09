<?php

declare(strict_types=1);

namespace Tests\Feature\ControlRoom;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Vista di sola lettura sull'audit log dalla Control Room — colma uno dei 3
 * gap ESSENZIALI segnalati in CONTROL_ROOM_ROADMAP.md ("Cronologia/audit
 * log"): i dati esistevano già in `audit_logs`, mancava solo la schermata.
 */
final class AuditLogViewTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function actingSuperAdmin(): User
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_audit_log_lists_recorded_entries(): void
    {
        $this->actingSuperAdmin();

        DB::table('audit_logs')->insert([
            'action' => 'tenant.suspended',
            'tenant_id' => 42,
            'actor_user_id' => 1,
            'created_at' => now(),
        ]);
        DB::table('audit_logs')->insert([
            'action' => 'control_room.login',
            'actor_user_id' => 1,
            'created_at' => now(),
        ]);

        $this->get('/control-room/audit')
            ->assertOk()
            ->assertSee('tenant.suspended')
            ->assertSee('control_room.login');
    }

    public function test_audit_log_can_be_filtered_by_action(): void
    {
        $this->actingSuperAdmin();

        DB::table('audit_logs')->insert([
            'action' => 'tenant.suspended',
            'created_at' => now(),
        ]);
        DB::table('audit_logs')->insert([
            'action' => 'control_room.login',
            'created_at' => now(),
        ]);

        $this->get('/control-room/audit?action=tenant')
            ->assertOk()
            ->assertSee('tenant.suspended')
            ->assertDontSee('control_room.login');
    }
}
