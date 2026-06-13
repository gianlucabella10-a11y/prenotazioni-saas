<?php

declare(strict_types=1);

namespace App\Foundation\Http\Middleware;

use App\Foundation\Enums\UserType;
use App\Foundation\Http\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coarse-grained RBAC at the routing layer (docs/26 §5): restricts a route
 * group to the given user types. Fine-grained, per-resource authorization
 * stays in policies/use cases.
 *
 * Usage: ->middleware('user.type:tenant_admin,staff')
 */
final class EnsureUserType
{
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw ApiException::unauthorized();
        }

        $allowed = array_map(
            static function (string $t): UserType {
                // A typo in a route definition must fail loudly at first hit,
                // not silently grant or deny access.
                return UserType::tryFrom($t)
                    ?? throw new \InvalidArgumentException("Unknown user type '{$t}' in route middleware.");
            },
            $types,
        );

        if (! in_array($user->type, $allowed, true)) {
            throw ApiException::forbidden();
        }

        return $next($request);
    }
}
