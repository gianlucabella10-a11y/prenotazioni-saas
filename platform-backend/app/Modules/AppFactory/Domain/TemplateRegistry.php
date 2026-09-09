<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Domain;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Registry dei template white-label (skin curate). Unico punto di accesso a
 * `config/app_templates.php`: normalizza ogni template, garantisce un
 * **fallback al default** su codice o config invalida, ed espone layout/font/
 * sezioni. È una SKIN: non tocca booking/auth/API. Nessun `if cliente==X`.
 */
final readonly class TemplateRegistry
{
    public const DEFAULT_CODE = 'default';

    /** Fallback sicuro se la config è assente o malformata. */
    private const HARD_DEFAULT = [
        'label' => 'Standard',
        'vertical' => 'other',
        'theme' => ['colors' => ['primary' => '#1F2937', 'secondary' => '#C8A24B']],
        'font_style' => 'inter',
        'layout_variant' => 'standard',
        'sections' => ['header', 'services', 'staff', 'hours', 'contacts'],
    ];

    public function __construct(private Config $config) {}

    /** @return array<string, array<string, mixed>> tutti i template normalizzati */
    public function all(): array
    {
        $raw = $this->config->get('app_templates');

        if (! is_array($raw) || $raw === []) {
            return [self::DEFAULT_CODE => self::HARD_DEFAULT];
        }

        $out = [];

        foreach ($raw as $code => $conf) {
            if (is_string($code) && is_array($conf)) {
                $out[$code] = $this->normalize($conf);
            }
        }

        return $out === [] ? [self::DEFAULT_CODE => self::HARD_DEFAULT] : $out;
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->all());
    }

    public function has(string $code): bool
    {
        return array_key_exists($code, $this->all());
    }

    /**
     * Template normalizzato per `code`; se assente/invalido → default.
     *
     * @return array<string, mixed>
     */
    public function get(string $code): array
    {
        $all = $this->all();

        return $all[$code] ?? $all[self::DEFAULT_CODE] ?? self::HARD_DEFAULT;
    }

    public function label(string $code): string
    {
        return (string) $this->get($code)['label'];
    }

    public function layout(string $code): string
    {
        return (string) $this->get($code)['layout_variant'];
    }

    public function fontStyle(string $code): ?string
    {
        $font = $this->get($code)['font_style'] ?? null;

        return $font === null ? null : (string) $font;
    }

    /** @return list<string> sezioni e ordine per il template */
    public function sections(string $code): array
    {
        return array_values($this->get($code)['sections']);
    }

    /** @param array<string, mixed> $conf @return array<string, mixed> */
    private function normalize(array $conf): array
    {
        $sections = $conf['sections'] ?? null;

        return [
            'label' => (string) ($conf['label'] ?? self::HARD_DEFAULT['label']),
            'vertical' => (string) ($conf['vertical'] ?? self::HARD_DEFAULT['vertical']),
            'theme' => is_array($conf['theme'] ?? null) ? $conf['theme'] : self::HARD_DEFAULT['theme'],
            'font_style' => $conf['font_style'] ?? self::HARD_DEFAULT['font_style'],
            'layout_variant' => (string) ($conf['layout_variant'] ?? self::HARD_DEFAULT['layout_variant']),
            'sections' => is_array($sections) && $sections !== [] ? array_values($sections) : self::HARD_DEFAULT['sections'],
        ];
    }
}
