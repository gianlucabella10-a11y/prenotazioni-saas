<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Branding\Application\ContrastValidator;
use App\Modules\Branding\Application\DeriveDarkPalette;
use Tests\TestCase;

/**
 * DeriveDarkPalette produce una palette dark leggibile: sfondi/superfici dark
 * fissi e ogni colore di brand/semantico con contrasto ≥ soglia sullo sfondo.
 */
final class DeriveDarkPaletteTest extends TestCase
{
    private function deriver(): DeriveDarkPalette
    {
        return new DeriveDarkPalette(new ContrastValidator());
    }

    public function test_uses_curated_dark_neutrals(): void
    {
        $dark = $this->deriver()->fromLight([
            'primary' => '#1F2937',
            'secondary' => '#C8A24B',
        ]);

        self::assertSame('#0B1220', $dark['background']);
        self::assertSame('#111827', $dark['surface']);
        self::assertSame('#E5E7EB', $dark['on_surface']);
    }

    public function test_dark_navy_primary_is_lightened_until_readable_on_dark(): void
    {
        $contrast = new ContrastValidator();

        // Navy quasi nero: invisibile sullo sfondo dark se non schiarito.
        $dark = (new DeriveDarkPalette($contrast))->fromLight([
            'primary' => '#0B1220',
            'on_primary' => '#FFFFFF',
            'secondary' => '#111827',
        ]);

        self::assertGreaterThanOrEqual(
            3.0,
            $contrast->ratio($dark['primary'], '#0B1220'),
            'la primary dark deve staccare dallo sfondo dark',
        );
        self::assertGreaterThanOrEqual(
            3.0,
            $contrast->ratio($dark['secondary'], '#0B1220'),
        );
    }

    public function test_already_light_color_is_left_unchanged(): void
    {
        $dark = $this->deriver()->fromLight([
            'primary' => '#F5F5F5', // già chiarissimo → nessuna modifica
            'secondary' => '#C8A24B',
        ]);

        self::assertSame('#F5F5F5', $dark['primary']);
    }

    public function test_all_expected_tokens_are_present(): void
    {
        $dark = $this->deriver()->fromLight(config('branding.default_theme.colors'));

        foreach (['primary', 'on_primary', 'secondary', 'accent', 'surface', 'background', 'success', 'warning', 'error'] as $token) {
            self::assertArrayHasKey($token, $dark, "token dark {$token} mancante");
            self::assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $dark[$token]);
        }
    }
}
