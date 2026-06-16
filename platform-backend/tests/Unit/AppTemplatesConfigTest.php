<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

/** I template white-label curati esistono con le chiavi attese. */
final class AppTemplatesConfigTest extends TestCase
{
    public function test_curated_templates_have_required_keys(): void
    {
        $templates = (array) config('app_templates');

        foreach (['default', 'barber_dark', 'beauty_visual', 'medical_clean', 'restaurant_visual'] as $code) {
            self::assertArrayHasKey($code, $templates, "template {$code} mancante");
            self::assertArrayHasKey('label', $templates[$code]);
            self::assertArrayHasKey('layout_variant', $templates[$code]);
            self::assertArrayHasKey('font_style', $templates[$code]);
            self::assertArrayHasKey('theme', $templates[$code]);
        }
    }
}
