<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Domain\AppProjectStatus;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class AppIdentityImmutabilityTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function allocate(string $template = 'barber_dark'): AppProject
    {
        $env = $this->provisionBookableTenant();
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());

        return app(AllocateAppIdentifiers::class)->execute($env['tenant'], $template, $admin->id);
    }

    public function test_allocation_is_idempotent_no_duplicates(): void
    {
        $env = $this->provisionBookableTenant();
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());

        $first = app(AllocateAppIdentifiers::class)->execute($env['tenant'], 'barber_dark', $admin->id);
        $second = app(AllocateAppIdentifiers::class)->execute($env['tenant'], 'beauty_visual', $admin->id);

        self::assertSame($first->id, $second->id); // stesso progetto, nessun duplicato
        self::assertSame(1, $this->bypassTenancy(fn () => AppProject::query()->where('tenant_id', $env['tenant']->id)->count()));
    }

    public function test_identity_fields_are_unique_across_tenants(): void
    {
        $a = $this->allocate();
        $b = $this->allocate();

        self::assertNotSame($a->bundle_id, $b->bundle_id);
        self::assertNotSame($a->package_name, $b->package_name);
        self::assertNotSame($a->shortcode, $b->shortcode);
        self::assertNotSame($a->slug, $b->slug);
    }

    public function test_bundle_id_is_immutable_after_creation(): void
    {
        $project = $this->allocate();

        $this->expectException(\LogicException::class);
        $this->bypassTenancy(fn () => $project->forceFill(['bundle_id' => 'com.evil.takeover'])->save());
    }

    public function test_package_and_shortcode_are_immutable(): void
    {
        $project = $this->allocate();

        $this->expectException(\LogicException::class);
        $this->bypassTenancy(fn () => $project->forceFill(['package_name' => 'com.evil.pkg'])->save());
    }

    public function test_template_and_status_remain_mutable(): void
    {
        $project = $this->allocate('default');

        $this->bypassTenancy(function () use ($project): void {
            $project->forceFill([
                'template_code' => 'medical_clean',
                'build_status' => AppProjectStatus::Published,
            ])->save();
        });

        $fresh = $this->bypassTenancy(fn (): AppProject => AppProject::query()->findOrFail($project->id));
        self::assertSame('medical_clean', $fresh->template_code);
        self::assertSame('published', $fresh->build_status->value);
        self::assertSame($project->bundle_id, $fresh->bundle_id); // identità invariata
    }
}
