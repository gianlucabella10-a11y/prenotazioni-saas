<?php

declare(strict_types=1);

namespace App\Foundation\Http;

use Exception;

/**
 * Base class for every error the API intentionally returns.
 *
 * Rendered uniformly as {"error": {"code", "message", "details"}} with a
 * stable, documented machine-readable code (docs/25 §1). Unexpected
 * exceptions never expose internals: they render as internal_error.
 */
class ApiException extends Exception
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function notFound(string $resource = 'resource'): self
    {
        return new self('not_found', "The requested {$resource} does not exist.", 404);
    }

    public static function forbidden(string $code = 'forbidden', string $message = 'This action is not allowed.'): self
    {
        return new self($code, $message, 403);
    }

    /** @param array<string, mixed> $details */
    public static function conflict(string $code, string $message, array $details = []): self
    {
        return new self($code, $message, 409, $details);
    }

    /** @param array<string, mixed> $details */
    public static function unprocessable(string $code, string $message, array $details = []): self
    {
        return new self($code, $message, 422, $details);
    }

    public static function unauthorized(string $code = 'unauthenticated', string $message = 'Authentication required.'): self
    {
        return new self($code, $message, 401);
    }
}
