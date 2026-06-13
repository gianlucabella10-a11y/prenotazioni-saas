<?php

declare(strict_types=1);

namespace App\Foundation\Http;

use Illuminate\Http\JsonResponse;

/**
 * Uniform API error envelope: {"error": {"code", "message", "details"}}
 * with stable machine-readable codes (docs/25 §1).
 */
final class ApiErrorResponse
{
    /** @param array<string, mixed> $details */
    public static function make(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return new JsonResponse(['error' => $error], $status);
    }
}
