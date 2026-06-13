<?php

declare(strict_types=1);

namespace App\Foundation\Http\Middleware;

use App\Foundation\Http\ApiException;
use App\Foundation\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backend enforcement of plan feature flags (docs/28 §4): a modified client
 * cannot reach features the tenant's plan does not include.
 *
 * Usage: ->middleware('feature:waitlist')
 */
final class RequiresFeature
{
    public function __construct(private readonly CurrentTenant $currentTenant)
    {
    }

    public function handle(Request $request, Closure $next, string $featureCode): Response
    {
        if (! $this->currentTenant->get()->hasFeature($featureCode)) {
            throw ApiException::forbidden(
                'feature_not_available',
                'This feature is not included in the current plan.'
            );
        }

        return $next($request);
    }
}
