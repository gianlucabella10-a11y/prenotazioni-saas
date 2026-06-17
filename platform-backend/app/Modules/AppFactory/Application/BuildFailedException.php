<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application;

use RuntimeException;

/**
 * Errore di build che porta con sé l'osservabilità (comando, log, exit code,
 * durata) così il worker può tracciarla anche sul fallimento.
 */
final class BuildFailedException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $command,
        public readonly string $log,
        public readonly int $exitCode,
        public readonly int $durationMs,
    ) {
        parent::__construct($message);
    }
}
