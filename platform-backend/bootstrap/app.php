<?php

declare(strict_types=1);

use App\Foundation\Http\ApiErrorResponse;
use App\Foundation\Http\ApiException;
use App\Foundation\Http\Middleware\EnsureFullAuthentication;
use App\Foundation\Http\Middleware\EnsureTenantOperating;
use App\Foundation\Http\Middleware\EnsureUserType;
use App\Foundation\Http\Middleware\RequiresFeature;
use App\Foundation\Http\Middleware\ResolveTenantFromKey;
use App\Modules\Scheduling\Domain\Exceptions\InvalidStatusTransition;
use App\Modules\Scheduling\Domain\Exceptions\SlotUnavailable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.key' => ResolveTenantFromKey::class,
            'tenant.operating' => EnsureTenantOperating::class,
            'user.type' => EnsureUserType::class,
            'auth.full' => EnsureFullAuthentication::class,
            'feature' => RequiresFeature::class,
            'verified' => \App\Foundation\Http\Middleware\EnsureEmailVerified::class,
            'tenant.dashboard' => \App\Modules\Dashboard\Http\Middleware\BindDashboardTenant::class,
            'owner' => \App\Modules\Dashboard\Http\Middleware\RequireOwner::class,
        ]);

        $middleware->redirectGuestsTo('/dashboard/login');
        $middleware->redirectUsersTo('/dashboard/home');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Crash reporting (condizione GO-beta): no-op finché SENTRY_LARAVEL_DSN
        // non è valorizzata nell'ambiente — nessun dato lascia la macchina in dev.
        \Sentry\Laravel\Integration::handles($exceptions);

        $isApi = static fn (Request $request): bool => $request->is('api/*');

        $exceptions->shouldRenderJsonWhen($isApi);

        $exceptions->render(function (ApiException $e, Request $request) use ($isApi) {
            return $isApi($request) ? ApiErrorResponse::make($e->errorCode, $e->getMessage(), $e->status, $e->details) : null;
        });

        $exceptions->render(function (SlotUnavailable $e, Request $request) use ($isApi) {
            return $isApi($request) ? ApiErrorResponse::make('slot_unavailable', $e->getMessage(), 409) : null;
        });

        $exceptions->render(function (InvalidStatusTransition $e, Request $request) use ($isApi) {
            return $isApi($request) ? ApiErrorResponse::make('invalid_status_transition', $e->getMessage(), 422) : null;
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            return $isApi($request)
                ? ApiErrorResponse::make('validation_failed', 'The request payload is invalid.', 422, $e->errors())
                : null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            return $isApi($request) ? ApiErrorResponse::make('unauthenticated', 'Authentication required.', 401) : null;
        });

        // Cross-tenant uuid probing must be indistinguishable from a missing
        // resource (docs/28 §2, level 5): always a generic 404.
        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) use ($isApi) {
            return $isApi($request) ? ApiErrorResponse::make('not_found', 'The requested resource does not exist.', 404) : null;
        });
    })->create();
