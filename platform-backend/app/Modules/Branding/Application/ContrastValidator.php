<?php

declare(strict_types=1);

namespace App\Modules\Branding\Application;

/**
 * WCAG 2.x relative-luminance contrast check (docs/05 RF-12): the Brand
 * Studio refuses palettes whose primary/on-primary contrast falls below the
 * configured AA threshold.
 */
final class ContrastValidator
{
    public function ratio(string $hexA, string $hexB): float
    {
        $lumA = $this->relativeLuminance($hexA);
        $lumB = $this->relativeLuminance($hexB);

        [$light, $dark] = $lumA >= $lumB ? [$lumA, $lumB] : [$lumB, $lumA];

        return ($light + 0.05) / ($dark + 0.05);
    }

    public function meetsMinimum(string $hexA, string $hexB, float $minimumRatio): bool
    {
        return $this->ratio($hexA, $hexB) >= $minimumRatio;
    }

    private function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');

        $channels = [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];

        $linear = array_map(
            static fn (float $c): float => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
