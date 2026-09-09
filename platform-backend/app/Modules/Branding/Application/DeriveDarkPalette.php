<?php

declare(strict_types=1);

namespace App\Modules\Branding\Application;

/**
 * Deriva una palette DARK leggibile a partire dalla palette light del tenant.
 * Riusa {@see ContrastValidator} (nessun nuovo stack): sfondi/superfici dark
 * fissi e curati, testi chiari; i colori di brand (primary/secondary/accent)
 * e semantici (success/warning/error) vengono schiariti verso il bianco finché
 * non superano la soglia di contrasto minima sullo sfondo dark. Così anche un
 * brand con primary molto scuro (es. navy) resta visibile in dark mode.
 *
 * È il complemento del default curato in config/branding.php: quel default
 * copre i tenant nuovi, questo copre i tenant con palette light personalizzata
 * che abilitano il dark senza averne fornita una.
 */
final readonly class DeriveDarkPalette
{
    /** Neutri dark curati (coerenti col default_theme.dark di branding.php). */
    private const BACKGROUND = '#0B1220';
    private const SURFACE = '#111827';
    private const ON_SURFACE = '#E5E7EB';

    /** Contrasto minimo per elementi UI/large text (WCAG 1.4.11 / 1.4.3 large). */
    private const MIN_CONTRAST = 3.0;

    public function __construct(private ContrastValidator $contrast) {}

    /**
     * @param  array<string, string>  $light  palette light (theme.colors)
     * @return array<string, string>  palette dark leggibile
     */
    public function fromLight(array $light): array
    {
        $onPrimary = $light['on_primary'] ?? '#FFFFFF';
        $onSecondary = $light['on_secondary'] ?? '#1F2937';
        $onAccent = $light['on_accent'] ?? $onSecondary;

        return [
            'primary' => $this->readableOnDark($light['primary'] ?? '#1F2937'),
            'on_primary' => $onPrimary,
            'secondary' => $this->readableOnDark($light['secondary'] ?? '#C8A24B'),
            'on_secondary' => $onSecondary,
            'accent' => $this->readableOnDark($light['accent'] ?? $light['secondary'] ?? '#C8A24B'),
            'on_accent' => $onAccent,
            'surface' => self::SURFACE,
            'on_surface' => self::ON_SURFACE,
            'background' => self::BACKGROUND,
            'success' => $this->readableOnDark($light['success'] ?? '#15803D'),
            'warning' => $this->readableOnDark($light['warning'] ?? '#B45309'),
            'error' => $this->readableOnDark($light['error'] ?? '#B91C1C'),
        ];
    }

    /**
     * Schiarisce `$hex` verso il bianco finché non raggiunge il contrasto
     * minimo sullo sfondo dark. Se già sufficiente, lo restituisce invariato.
     */
    private function readableOnDark(string $hex): string
    {
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
            return $hex;
        }

        $current = $hex;

        // Max 10 passi da ~9% di miscelazione: converge ben prima per ogni
        // colore reale, e non supera mai il bianco puro.
        for ($step = 0; $step < 10; $step++) {
            if ($this->contrast->meetsMinimum($current, self::BACKGROUND, self::MIN_CONTRAST)) {
                return $current;
            }

            $current = $this->mixTowardWhite($current, 0.12);
        }

        return $current;
    }

    /** Miscela lineare verso #FFFFFF di un fattore $amount (0..1). */
    private function mixTowardWhite(string $hex, float $amount): string
    {
        $hex = ltrim($hex, '#');

        $mixed = array_map(
            static function (int $offset) use ($hex, $amount): int {
                $channel = (int) hexdec(substr($hex, $offset, 2));

                return (int) round($channel + (255 - $channel) * $amount);
            },
            [0, 2, 4],
        );

        return sprintf('#%02X%02X%02X', $mixed[0], $mixed[1], $mixed[2]);
    }
}
