<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\AppFactory\Application\BuildFleet;
use Illuminate\Console\Command;

/**
 * App Factory (FASE 3 — scala): stampa in JSON gli UUID dei tenant da
 * costruire. Consumato dalla CI matrix (`strategy.matrix` via `fromJSON`) per
 * costruire la flotta a lotti. NON esegue build.
 *
 *   php artisan app:build-matrix --platform=android [--stale-only] [--limit=10]
 *
 * --stale-only : solo app mai costruite o con un core precedente (release train)
 * --limit      : numero massimo (canary: lancia prima un lotto piccolo)
 */
final class BuildMatrix extends Command
{
    protected $signature = 'app:build-matrix {--platform=android} {--stale-only} {--limit=}';

    protected $description = 'Stampa (JSON) gli UUID dei tenant da costruire per la CI matrix (FASE 3). Nessuna build.';

    public function handle(BuildFleet $fleet): int
    {
        $platform = (string) $this->option('platform');

        if (! in_array($platform, ['android', 'ios'], true)) {
            $this->error('Piattaforma non valida (android|ios).');

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $uuids = $fleet->matrix(
            (bool) $this->option('stale-only'),
            $limit !== null && $limit !== '' ? (int) $limit : null,
        );

        // Solo JSON su stdout: la CI lo cattura con fromJSON.
        $this->output->writeln((string) json_encode(array_values($uuids)));

        return self::SUCCESS;
    }
}
