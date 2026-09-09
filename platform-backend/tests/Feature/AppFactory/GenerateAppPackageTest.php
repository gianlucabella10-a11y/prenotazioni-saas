<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Application\GenerateAppPackage;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class GenerateAppPackageTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function adminId(): int
    {
        return $this->bypassTenancy(fn (): int => User::factory()->superAdmin()->create()->id);
    }

    public function test_generates_manifest_build_and_status(): void
    {
        Storage::fake('local');
        $env = $this->provisionBookableTenant();
        $adminId = $this->adminId();
        $project = app(AllocateAppIdentifiers::class)->execute($env['tenant'], 'barber_dark', $adminId);

        $build = app(GenerateAppPackage::class)->execute($project, $adminId);

        self::assertSame('config', $build->platform);
        self::assertSame('generated', $build->status);
        Storage::disk('local')->assertExists($build->artifact_path);

        $fresh = $this->bypassTenancy(fn (): AppProject => AppProject::query()->findOrFail($project->id));
        // Nessun logo → nessun asset → stato "generated" (non ready_to_build).
        self::assertSame('generated', $fresh->build_status->value);

        // Manifest 2.0: sezioni strutturate.
        self::assertSame('2.0', $fresh->build_manifest['metadata']['manifest_schema']);
        self::assertSame($env['tenant']->api_key, $fresh->build_manifest['runtime']['dart_define']['TENANT_KEY']);
        self::assertSame($project->bundle_id, $fresh->build_manifest['app_identity']['bundle_id']);
        self::assertSame('barber_dark', $fresh->build_manifest['template']['template_code']);
    }

    public function test_version_increments_on_regenerate(): void
    {
        Storage::fake('local');
        $env = $this->provisionBookableTenant();
        $adminId = $this->adminId();
        $project = app(AllocateAppIdentifiers::class)->execute($env['tenant'], 'default', $adminId);

        app(GenerateAppPackage::class)->execute($project, $adminId);
        app(GenerateAppPackage::class)->execute($project, $adminId);

        self::assertSame(2, $this->bypassTenancy(
            fn () => AppBuild::query()->where('app_project_id', $project->id)->count()
        ));
    }
}
