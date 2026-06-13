<?php

declare(strict_types=1);

namespace App\Foundation\Auth;

use App\Foundation\Tenancy\CurrentTenant;
use App\Foundation\Tenancy\TenantRegistry;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

/**
 * Stateless guard for the `api` surface.
 *
 * On user() it validates the bearer token, loads the user and binds the
 * TenantContext from the signed `tid` claim (docs/28 §2, defence level 1:
 * the tenant always derives from the token, never from request input).
 *
 * The guard instance outlives a single request in long-lived runtimes
 * (queue workers, Octane, test kernels): the request is resolved lazily and
 * authentication is memoized PER TOKEN, never per instance.
 */
final class JwtGuard implements Guard
{
    private ?User $user = null;

    private ?JwtClaims $claims = null;

    /** Bearer token the memoized state belongs to. */
    private ?string $resolvedForToken = null;

    /**
     * Request instance the memoization belongs to: a NEW request must always
     * re-authenticate, even with the same token — account status may have
     * changed in the meantime (e.g. in-app deletion must take effect on the
     * very next call, not at access-token expiry).
     */
    private ?int $resolvedForRequestId = null;

    public function __construct(
        private readonly JwtService $jwt,
        private readonly TenantRegistry $registry,
        private readonly Container $container,
    ) {
    }

    public function user(): ?Authenticatable
    {
        $request = $this->currentRequest();
        $token = $request->bearerToken();
        $requestId = spl_object_id($request);

        if ($token === $this->resolvedForToken && $requestId === $this->resolvedForRequestId) {
            return $this->user;
        }

        $this->resolvedForToken = $token;
        $this->resolvedForRequestId = $requestId;
        $this->user = null;
        $this->claims = null;

        if ($token === null || $token === '') {
            return null;
        }

        try {
            $claims = $this->jwt->validate($token);
        } catch (\App\Foundation\Http\ApiException) {
            return null; // unauthenticated; the auth middleware renders 401
        }

        $user = User::query()->where('uuid', $claims->userUuid)->first();

        if ($user === null || $user->status !== 'active') {
            return null;
        }

        // Defence in depth: the signed tenant claim must match the user row.
        if ($user->tenant_id !== $claims->tenantId) {
            return null;
        }

        if ($claims->tenantId !== null && ! $this->bindTenant($claims->tenantId)) {
            return null;
        }

        $this->claims = $claims;
        $this->user = $user;

        return $this->user;
    }

    public function claims(): ?JwtClaims
    {
        $this->user();

        return $this->claims;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        return false; // credential validation belongs to AuthenticationService
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user instanceof User ? $user : null;
        $this->resolvedForToken = $this->currentRequest()->bearerToken();
        $this->resolvedForRequestId = spl_object_id($this->currentRequest());

        return $this;
    }

    /**
     * Bind (or re-bind) the tenant context for the token's tenant. A stale
     * context from a previous request in the same runtime is replaced.
     */
    private function bindTenant(int $tenantId): bool
    {
        /** @var CurrentTenant $current */
        $current = $this->container->make(CurrentTenant::class);

        if ($current->bound() && $current->id() === $tenantId) {
            return true;
        }

        $context = $this->registry->findById($tenantId);

        if ($context === null) {
            return false;
        }

        $current->set($context);

        return true;
    }

    private function currentRequest(): Request
    {
        return $this->container->make('request');
    }
}
