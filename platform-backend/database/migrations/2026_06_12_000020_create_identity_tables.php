<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity & access: every authenticatable identity (super admin, tenant
 * admin, staff, customer) lives in `users`, partitioned by tenant_id
 * (NULL = platform-level user). MFA credentials, rotating refresh tokens
 * and push-capable devices hang off users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20)->index();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('password')->nullable();
            $table->string('social_provider', 20)->nullable();
            $table->string('social_id')->nullable();
            $table->boolean('mfa_enforced')->default(false);
            $table->string('locale', 10);
            $table->string('status', 20);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            // NULL tenant_id rows (platform users) escape these constraints in
            // MySQL/SQLite; platform-level uniqueness is enforced in validation.
            $table->unique(['tenant_id', 'email']);
            $table->unique(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('mfa_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('secret'); // encrypted at rest via model cast
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'type']);
        });

        Schema::create('refresh_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // SHA-256 of the opaque token; the clear value is never stored.
            $table->char('token_hash', 64)->unique();
            // All rotations of one login session share a family; reuse of a
            // rotated token revokes the whole family (theft detection).
            $table->uuid('family_uuid')->index();
            $table->string('device_label')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('rotated_from_id')->nullable()->references('id')->on('refresh_tokens')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 10);
            $table->string('fcm_token', 255);
            $table->string('locale', 10)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'fcm_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
        Schema::dropIfExists('refresh_tokens');
        Schema::dropIfExists('mfa_credentials');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
