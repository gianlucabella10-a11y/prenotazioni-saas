<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Application\TransitionAppProject;
use App\Modules\AppFactory\Domain\AppProjectStatus;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class TransitionAppProjectTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function project(): AppProject
    {
        $env = $this->provisionBookableTenant();
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());

        return app(AllocateAppIdentifiers::class)->execute($env['tenant'], 'barber_dark', $admin->id);
    }

    public function test_transition_changes_status_and_audits_old_new(): void
    {
        $project = $this->project();

        app(TransitionAppProject::class)->execute($project, AppProjectStatus::ReadyToBuild, null);

        self::assertSame('ready_to_build', $this->bypassTenancy(fn (): string => $project->fresh()->build_status->value));

        $log = DB::table('audit_logs')->where('action', 'app_project.status_changed')->latest('id')->first();
        self::assertNotNull($log);
        $payload = json_decode((string) $log->payload, true);
        self::assertSame('draft', $payload['from']);
        self::assertSame('ready_to_build', $payload['to']);
    }

    public function test_idempotent_transition_emits_no_audit(): void
    {
        $project = $this->project(); // draft

        app(TransitionAppProject::class)->execute($project, AppProjectStatus::Draft, null);

        self::assertSame(0, DB::table('audit_logs')->where('action', 'app_project.status_changed')->count());
    }

    public function test_mark_configured_only_from_draft(): void
    {
        $project = $this->project();

        $configured = app(TransitionAppProject::class)->markConfigured($project, null);
        self::assertSame('configured', $configured->build_status->value);

        // Da uno stato avanzato non torna a configured.
        app(TransitionAppProject::class)->execute($project, AppProjectStatus::ReadyToBuild, null);
        $again = app(TransitionAppProject::class)->markConfigured($project->fresh(), null);
        self::assertSame('ready_to_build', $again->build_status->value);
    }
}
