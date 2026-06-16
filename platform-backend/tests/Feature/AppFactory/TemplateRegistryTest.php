<?php

declare(strict_types=1);

namespace Tests\Feature\AppFactory;

use App\Modules\AppFactory\Domain\TemplateRegistry;
use Tests\TestCase;

final class TemplateRegistryTest extends TestCase
{
    private function registry(): TemplateRegistry
    {
        return app(TemplateRegistry::class);
    }

    public function test_known_template_exposes_layout_font_sections(): void
    {
        $r = $this->registry();

        self::assertTrue($r->has('barber_dark'));
        self::assertSame('hero_dark', $r->layout('barber_dark'));
        self::assertSame('oswald', $r->fontStyle('barber_dark'));
        self::assertNotEmpty($r->sections('barber_dark'));
    }

    public function test_unknown_code_falls_back_to_default(): void
    {
        $r = $this->registry();

        self::assertFalse($r->has('does_not_exist'));
        self::assertSame($r->layout('default'), $r->layout('does_not_exist'));
        self::assertSame($r->sections('default'), $r->sections('does_not_exist'));
    }

    public function test_invalid_config_uses_hard_default(): void
    {
        config(['app_templates' => 'broken']); // non-array → config invalida

        $r = $this->registry();

        self::assertSame(['default'], $r->codes());
        self::assertSame('standard', $r->layout('default'));
        self::assertSame(['header', 'services', 'staff', 'hours', 'contacts'], $r->sections('default'));
    }

    public function test_codes_lists_all_curated_templates(): void
    {
        $codes = $this->registry()->codes();

        self::assertContains('beauty_visual', $codes);
        self::assertContains('medical_clean', $codes);
    }
}
