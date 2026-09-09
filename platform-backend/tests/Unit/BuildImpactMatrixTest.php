<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\AppFactory\Domain\BuildImpactMatrix;
use Tests\TestCase;

/**
 * Smart Build Matrix (Fase 9): la classificazione runtime/build è coerente e
 * copre le aree chiave della personalizzazione.
 */
final class BuildImpactMatrixTest extends TestCase
{
    private function matrix(): BuildImpactMatrix
    {
        return app(BuildImpactMatrix::class);
    }

    public function test_runtime_areas_are_classified_as_runtime(): void
    {
        $matrix = $this->matrix();

        foreach (['colors', 'theme_mode', 'content', 'booking_policy', 'notification_style', 'logo_in_app'] as $area) {
            self::assertSame(BuildImpactMatrix::RUNTIME, $matrix->classify($area), "{$area} deve essere runtime");
        }
    }

    public function test_build_areas_are_classified_as_build(): void
    {
        $matrix = $this->matrix();

        foreach (['app_icon', 'splash_native', 'font_family', 'bundle_id', 'api_base_url'] as $area) {
            self::assertSame(BuildImpactMatrix::BUILD, $matrix->classify($area), "{$area} deve richiedere build");
        }
    }

    public function test_unknown_area_is_null(): void
    {
        self::assertNull($this->matrix()->classify('does_not_exist'));
    }

    public function test_requires_build_is_true_if_any_area_needs_build(): void
    {
        $matrix = $this->matrix();

        self::assertFalse($matrix->requiresBuild(['colors', 'content', 'booking_policy']));
        self::assertTrue($matrix->requiresBuild(['colors', 'app_icon'])); // una basta
    }

    public function test_matrix_partitions_have_no_overlap(): void
    {
        $matrix = $this->matrix()->all();

        $overlap = array_intersect_key($matrix['runtime'], $matrix['build']);

        self::assertSame([], $overlap, 'un\'area non può essere sia runtime sia build');
    }
}
