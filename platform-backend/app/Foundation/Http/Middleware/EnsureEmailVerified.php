<?php

declare(strict_types=1);

namespace App\Foundation\Http\Middleware;

use App\Foundation\Http\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks identity-bound customer features until the email is verified
 * (Fase 1 — S1): an unverified account has no customer record, no history,
 * no booking rights. Stable code lets the app route to the verify screen.
 */
final class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->email_verified_at === null) {
            throw ApiException::forbidden(
                'email_not_verified',
                'Verify your email address to continue.'
            );
        }

        return $next($request);
    }
}
