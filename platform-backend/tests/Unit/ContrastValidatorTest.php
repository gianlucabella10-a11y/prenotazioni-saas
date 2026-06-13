<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Branding\Application\ContrastValidator;
use PHPUnit\Framework\TestCase;

final class ContrastValidatorTest extends TestCase
{
    private ContrastValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ContrastValidator();
    }

    public function test_black_on_white_is_max_contrast(): void
    {
        self::assertEqualsWithDelta(21.0, $this->validator->ratio('#000000', '#FFFFFF'), 0.01);
    }

    public function test_same_color_is_min_contrast(): void
    {
        self::assertEqualsWithDelta(1.0, $this->validator->ratio('#808080', '#808080'), 0.01);
    }

    public function test_ratio_is_symmetric(): void
    {
        self::assertSame(
            $this->validator->ratio('#1F2937', '#FFFFFF'),
            $this->validator->ratio('#FFFFFF', '#1F2937'),
        );
    }

    public function test_default_palette_meets_aa(): void
    {
        self::assertTrue($this->validator->meetsMinimum('#1F2937', '#FFFFFF', 4.5));
    }

    public function test_low_contrast_pair_fails_aa(): void
    {
        self::assertFalse($this->validator->meetsMinimum('#FFFF00', '#FFFFFF', 4.5));
    }
}
