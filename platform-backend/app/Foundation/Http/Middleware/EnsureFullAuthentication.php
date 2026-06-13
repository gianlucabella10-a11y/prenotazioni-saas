<?php

declare(strict_types=1);

namespace App\Foundation\Http\Middleware;

use App\Foundation\Auth\JwtGuard;
use App\Foundation\Http\ApiException;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects scope-limited tokens (MFA challenge tokens) on regular endpoints:
 * only the dedicated /auth/mfa/* routes accept them (docs/26 §4).
 */
final class EnsureFullAuthentication
{
    public function __construct(private readonly AuthFactory $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->auth->guard('api');

        $claims = $guard instanceof JwtGuard ? $guard->claims() : null;

        if ($claims === null || ! $claims->isFullyAuthenticated()) {
            throw ApiException::forbidden('mfa_required', 'Complete multi-factor authentication first.');
        }

        return $next($request);
    }
}
