<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Application\BuildDispatcher;
use App\Modules\AppFactory\Application\BuildDispatchResult;
use App\Modules\AppFactory\Application\Dispatchers\LocalBuildDispatcher;
use App\Modules\AppFactory\Application\PrepareApp;
use App\Modules\AppFactory\Application\RunAppBuildJob;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Pipeline di build sincrona (driver `local`): l'esito `completed` con artifact
 * reale porta la build a `built` con checksum e timeline. La fallita del driver
 * locale (es. Android SDK assente) marca `failed` con l'errore reale.
 */
final class BuildPipelineTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function queuedBuild(Tenant $tenant): AppBuild
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $project = app(AllocateAppIdentifiers::class)->execute($tenant, 'barber_dark', $admin->id);
        app(PrepareApp::class)->execute($project, $admin->id);

        return $this->bypassTenancy(fn (): AppBuild => AppBuild::query()->create([
            'tenant_id' => $tenant->id,
            'app_project_id' => $project->id,
            'version' => '1.0.0+1',
            'platform' => 'android',
            'status' => 'queued',
            'queued_at' => now(),
        ]));
    }

    public function test_completed_build_becomes_built_with_checksum_and_timeline(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config(['app_factory.build_driver' => 'local']);

        // Sostituiamo SOLO l'esecuzione nativa (richiede Android SDK) con un
        // artifact reale già prodotto: testiamo l'orchestrazione del job.
        $this->app->bind(LocalBuildDispatcher::class, fn (): BuildDispatcher => new class implements BuildDispatcher
        {
            public function dispatch(AppProject $project, string $platform): BuildDispatchResult
            {
                $path = "builds/{$project->tenant_id}/1.0.0+1/app-release.apk";
                Storage::disk('local')->put($path, 'REAL-APK');

                return BuildDispatchResult::built(
                    "local://{$path}",
                    $path,
                    hash('sha256', 'REAL-APK'),
                    'flutter build apk --release',
                    "$ flutter build apk\nBuilt build/app/outputs/flutter-apk/app-release.apk",
                    0,
                    4200,
                    7340032,
                );
            }

            public function name(): string
            {
                return 'local';
            }
        });

        $build = $this->queuedBuild($this->provisionBookableTenant()['tenant']);

        app()->call([new RunAppBuildJob($build->id), 'handle']);

        $fresh = $this->bypassTenancy(fn (): AppBuild => AppBuild::query()->findOrFail($build->id));
        self::assertSame('built', $fresh->status);
        self::assertSame(hash('sha256', 'REAL-APK'), $fresh->checksum);
        self::assertSame(4200, $fresh->duration_ms);
        self::assertSame(7340032, $fresh->size_bytes);
        self::assertNotNull($fresh->build_log);
        self::assertNotNull($fresh->finished_at);
        self::assertStringContainsString('builds/', (string) $fresh->artifact_path);
        self::assertSame('built', $this->bypassTenancy(fn (): string => AppProject::query()->findOrFail($fresh->app_project_id)->build_status->value));
    }

    public function test_local_driver_fails_when_app_dir_missing(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config([
            'app_factory.build_driver' => 'local',
            'app_factory.flutter_app_dir' => '/path/inesistente/client_app',
        ]);

        $build = $this->queuedBuild($this->provisionBookableTenant()['tenant']);

        app()->call([new RunAppBuildJob($build->id), 'handle']);

        $fresh = $this->bypassTenancy(fn (): AppBuild => AppBuild::query()->findOrFail($build->id));
        self::assertSame('failed', $fresh->status);
        self::assertNotNull($fresh->error_message);
        self::assertSame('failed', $this->bypassTenancy(fn (): string => AppProject::query()->findOrFail($fresh->app_project_id)->build_status->value));
    }
}
