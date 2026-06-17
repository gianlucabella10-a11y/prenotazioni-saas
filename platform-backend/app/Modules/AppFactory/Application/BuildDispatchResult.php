<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application;

/**
 * Esito di un dispatch di build. I driver remoti (`manual`/`github`) ritornano
 * un risultato NON completato (la compilazione gira altrove, l'esito rientra via
 * `app:build-record`). Il driver `local` compila qui e ritorna `completed` con
 * artifact reale, checksum e l'osservabilità (comando/log/exit code/durata).
 */
final readonly class BuildDispatchResult
{
    public function __construct(
        public string $reference,
        public bool $completed = false,
        public ?string $artifactPath = null,
        public ?string $checksum = null,
        public ?string $command = null,
        public ?string $log = null,
        public ?int $exitCode = null,
        public ?int $durationMs = null,
    ) {}

    /** Build avviata altrove (worker/CI): esito atteso via callback. */
    public static function remote(string $reference): self
    {
        return new self($reference);
    }

    /** Build completata localmente con artifact reale + osservabilità. */
    public static function built(
        string $reference,
        string $artifactPath,
        string $checksum,
        string $command,
        string $log,
        int $exitCode,
        int $durationMs,
    ): self {
        return new self($reference, true, $artifactPath, $checksum, $command, $log, $exitCode, $durationMs);
    }
}
