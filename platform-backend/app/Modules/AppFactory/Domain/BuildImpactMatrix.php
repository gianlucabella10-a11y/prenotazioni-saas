<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Domain;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Smart Build Matrix (Fase 9): classifica ogni area di personalizzazione come
 * `runtime` (nessuna build) o `build` (serve ricompilare). Unico punto di
 * accesso a config/personalization_matrix.php. Riutilizzabile ovunque serva
 * avvisare l'utente o decidere se una modifica richiede una nuova build.
 */
final readonly class BuildImpactMatrix
{
    public const RUNTIME = 'runtime';
    public const BUILD = 'build';

    public function __construct(private Config $config) {}

    /** @return array{runtime: array<string,string>, build: array<string,string>} */
    public function all(): array
    {
        $raw = (array) $this->config->get('personalization_matrix', []);

        return [
            self::RUNTIME => (array) ($raw[self::RUNTIME] ?? []),
            self::BUILD => (array) ($raw[self::BUILD] ?? []),
        ];
    }

    /** @return array<string,string> aree runtime (chiave => etichetta) */
    public function runtime(): array
    {
        return $this->all()[self::RUNTIME];
    }

    /** @return array<string,string> aree che richiedono build (chiave => etichetta) */
    public function build(): array
    {
        return $this->all()[self::BUILD];
    }

    /** `runtime` | `build` | null se l'area non è mappata. */
    public function classify(string $area): ?string
    {
        $matrix = $this->all();

        if (array_key_exists($area, $matrix[self::RUNTIME])) {
            return self::RUNTIME;
        }

        if (array_key_exists($area, $matrix[self::BUILD])) {
            return self::BUILD;
        }

        return null;
    }

    /**
     * Vero se ANCHE una sola delle aree modificate richiede una nuova build.
     *
     * @param  iterable<string>  $areas
     */
    public function requiresBuild(iterable $areas): bool
    {
        foreach ($areas as $area) {
            if ($this->classify($area) === self::BUILD) {
                return true;
            }
        }

        return false;
    }
}
