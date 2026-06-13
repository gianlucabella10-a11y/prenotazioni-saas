<?php

declare(strict_types=1);

namespace App\Providers;

use App\Foundation\Auth\JwtGuard;
use App\Foundation\Auth\JwtService;
use App\Foundation\Tenancy\CurrentTenant;
use App\Foundation\Tenancy\TenantRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request/job lifecycle (docs/28 §2).
        $this->app->scoped(CurrentTenant::class);
    }

    public function boot(): void
    {
        $this->registerJwtGuard();
        $this->registerRateLimiters();
        $this->registerDomainEventListeners();
    }

    /**
     * Cross-module communication happens through domain events only
     * (docs/22 §2): Scheduling publishes, Notifications subscribes.
     */
    private function registerDomainEventListeners(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \App\Modules\Scheduling\Application\Events\AppointmentBooked::class,
            [\App\Modules\Notifications\Application\ScheduleAppointmentNotifications::class, 'onBooked'],
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Modules\Scheduling\Application\Events\AppointmentCancelled::class,
            [\App\Modules\Notifications\Application\ScheduleAppointmentNotifications::class, 'onCancelled'],
        );
    }

    private function registerJwtGuard(): void
    {
        Auth::extend('jwt', function ($app, string $name, array $config): JwtGuard {
            return new JwtGuard(
                $app->make(JwtService::class),
                $app->make(TenantRegistry::class),
                $app,
            );
        });
    }

    /**
     * Rate limits per docs/25 §6: aggressive on credentials, generous on
     * availability (hottest read path), a per-tenant ceiling against noisy
     * neighbors.
     */
    private function registerRateLimiters(): void
    {
        $limits = config('api.rate_limits');

        RateLimiter::for('auth', function (Request $request) use ($limits) {
            return Limit::perMinute($limits['auth_per_ip'])->by('auth|' . $request->ip());
        });

        RateLimiter::for('availability', function (Request $request) use ($limits) {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute($limits['availability_per_user'])->by('avail|' . $key);
        });

        RateLimiter::for('api', function (Request $request) use ($limits) {
            $user = $request->user();

            if ($user === null) {
                return Limit::perMinute($limits['default_per_ip'])->by('api|ip|' . $request->ip());
            }

            return [
                Limit::perMinute($limits['default_per_user'])->by('api|user|' . $user->getAuthIdentifier()),
                Limit::perMinute($limits['tenant_ceiling'])->by('api|tenant|' . ($user->tenant_id ?? 'platform')),
            ];
        });
    }
}
