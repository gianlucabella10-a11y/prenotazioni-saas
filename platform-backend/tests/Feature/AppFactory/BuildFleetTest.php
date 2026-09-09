<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\BuildFleet;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class BuildFleetTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function project(string $status, ?string $core = null): AppProject
    {
        return $this->bypassTenancy(fn (): AppProject => AppProject::factory()->create([
            'build_status' => $status,
            'built_core_version' => $core,
        ]));
    }

    public function test_matrix_includes_only_buildable_statuses(): void
    {
        $rtb = $this->project('ready_to_build');
        $pub = $this->project('published', '1.0.0');
        $fail = $this->project('failed');
        $this->project('draft');
        $this->project('generated');
        $this->project('building');

        $uuids = app(BuildFleet::class)->matrix();

        self::assertEqualsCanonicalizing([$rtb->uuid, $pub->uuid, $fail->uuid], $uuids);
    }

    public function test_stale_only_filters_by_core_version(): void
    {
        config(['app_factory.core_version' => '2.0.0']);
        $this->project('published', '2.0.0');            // allineata → esclusa
        $behind = $this->project('published', '1.0.0');  // core precedente → stale
        $never = $this->project('ready_to_build', null); // mai costruita → stale

        $uuids = app(BuildFleet::class)->matrix(staleOnly: true);

        self::assertEqualsCanonicalizing([$behind->uuid, $never->uuid], $uuids);
    }

    public function test_limit_caps_for_canary(): void
    {
        $this->project('ready_to_build');
        $this->project('ready_to_build');
        $this->project('ready_to_build');

        self::assertCount(2, app(BuildFleet::class)->matrix(limit: 2));
    }

    public function test_summary_counts_stale_current_and_buildable(): void
    {
        config(['app_factory.core_version' => '1.0.0']);
        $this->project('published', '1.0.0'); // allineata
        $this->project('published', '0.9.0'); // stale
        $this->project('ready_to_build');      // stale (null), buildable
        $this->project('draft');               // non buildable

        $summary = app(BuildFleet::class)->summary();

        self::assertSame('1.0.0', $summary['current_core']);
        self::assertSame(1, $summary['on_current']);
        self::assertSame(2, $summary['stale']);
        self::assertSame(3, $summary['buildable']);
    }

    public function test_command_outputs_clean_json(): void
    {
        $project = $this->project('ready_to_build');

        Artisan::call('app:build-matrix', ['--platform' => 'android']);

        self::assertSame([$project->uuid], json_decode(trim(Artisan::output()), true));
    }

    public function test_command_rejects_invalid_platform(): void
    {
        $this->artisan('app:build-matrix', ['--platform' => 'web'])->assertFailed();
    }

    public function test_record_published_pins_core_version(): void
    {
        config(['app_factory.core_version' => '3.1.0']);
        $env = $this->provisionBookableTenant();
        $project = $this->bypassTenancy(fn (): AppProject => AppProject::factory()->create(['tenant_id' => $env['tenant']->id]));

        $this->artisan('app:build-record', [
            'tenant' => $env['tenant']->uuid,
            'platform' => 'android',
            'status' => 'published',
        ])->assertSuccessful();

        self::assertSame('3.1.0', $this->bypassTenancy(fn (): ?string => $project->fresh()->built_core_version));
    }

    public function test_fleet_dashboard_visible_to_super_admin(): void
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $this->project('published', '1.0.0');

        $this->actingAs($admin, 'admin')->get('/control-room/apps/flotta')->assertOk()->assertSee('Release train');
    }

    public function test_fleet_dashboard_guest_is_redirected(): void
    {
        $this->get('/control-room/apps/flotta')->assertRedirect(route('control.login'));
    }
}
