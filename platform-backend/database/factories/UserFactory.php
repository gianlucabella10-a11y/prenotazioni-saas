<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Foundation\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'type' => UserType::Customer,
            'email' => fake()->unique()->safeEmail(),
            // Verified by default: most scenarios exercise post-verification
            // behavior; the S1 flow uses the explicit unverified() state.
            'email_verified_at' => now(),
            'first_name' => fake()->firstName(),
            'password' => 'secret-password-123',
            'locale' => 'it',
            'status' => 'active',
            'mfa_enforced' => false,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    public function customer(): static
    {
        return $this->state(fn (): array => ['type' => UserType::Customer]);
    }

    public function staff(): static
    {
        return $this->state(fn (): array => ['type' => UserType::Staff]);
    }

    public function tenantAdmin(bool $mfaEnforced = false): static
    {
        return $this->state(fn (): array => [
            'type' => UserType::TenantAdmin,
            'mfa_enforced' => $mfaEnforced,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (): array => [
            'type' => UserType::SuperAdmin,
            'tenant_id' => null,
            'mfa_enforced' => false,
        ]);
    }
}
