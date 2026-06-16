<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

final class AllocateAppIdentifiersTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function adminId(): int
    {
        return $this->bypassTenancy(fn (): int => User::factory()->superAdmin()->create()->id);
    }

    public function test_allocates_unique_identifiers_from_template(): void
    {
        $env = $this->provisionBookableTenant();

        $project = app(AllocateAppIdentifiers::class)->execute($env['tenant'], 'barber_dark', $this->adminId());

        self::assertNotEmpty($project->bundle_id);
        self::assertSame($project->bundle_id, $project->package_name);
        self::assertStringContainsString('.t', $project->bundle_id);
        self::assertSame('barber_dark', $project->template_code);
        self::assertSame('oswald', $project->font_style);
        self::assertSame('draft', $project->build_status->value);
    }

    public function test_is_idempotent_per_tenant(): void
    {
        $env = $this->provisionBookableTenant();

        $a = app(AllocateAppIdentifiers::class)->execute($env['tenant'], 'default', $this->adminId());
        $b = app(AllocateAppIdentifiers::class)->execute($env['tenant'], 'beauty_visual', $this->adminId());

        self::assertSame($a->id, $b->id);
        self::assertSame(1, $this->bypassTenancy(
            fn () => AppProject::query()->where('tenant_id', $env['tenant']->id)->count()
        ));
    }

    public function test_two_tenants_get_distinct_identifiers(): void
    {
        $p1 = app(AllocateAppIdentifiers::class)->execute($this->provisionBookableTenant()['tenant'], 'default', $this->adminId());
        $p2 = app(AllocateAppIdentifiers::class)->execute($this->provisionBookableTenant()['tenant'], 'default', $this->adminId());

        self::assertNotSame($p1->bundle_id, $p2->bundle_id);
        self::assertNotSame($p1->shortcode, $p2->shortcode);
        self::assertNotSame($p1->slug, $p2->slug);
    }

    public function test_invalid_template_falls_back_to_default(): void
    {
        $env = $this->provisionBookableTenant();

        $project = app(AllocateAppIdentifiers::class)->execute($env['tenant'], 'does_not_exist', $this->adminId());

        self::assertSame('default', $project->template_code);
    }
}
