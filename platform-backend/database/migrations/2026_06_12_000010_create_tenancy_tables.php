<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenancy core: the tenant registry, custom domains, commercial plans,
 * subscriptions and per-tenant feature overrides.
 *
 * Status / enum-like columns are stored as strings and constrained by PHP
 * backed enums at the application layer: this keeps the schema portable
 * across MySQL and SQLite and avoids ALTER TABLE on enum evolution.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('legal_name');
            $table->string('display_name');
            $table->string('sector', 32);
            $table->string('status', 32)->index();
            $table->string('default_timezone', 64);
            $table->string('default_locale', 10);
            // Public key compiled into white label builds (X-Tenant-Key header).
            $table->string('api_key', 40)->unique();
            $table->json('onboarding_state')->nullable();
            // Operational parameters (reminder offsets, confirmation mode…),
            // validated by App\Foundation\Tenancy\TenantSettings.
            $table->json('settings')->nullable();
            $table->boolean('health_data_enabled')->default(false);
            $table->string('billing_email')->nullable();
            $table->string('vat_number', 32)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->unsignedInteger('price_monthly_cents');
            $table->char('currency', 3);
            // Default feature flags granted by the plan, e.g. {"waitlist": true}.
            $table->json('features');
            // Usage quotas, e.g. {"max_staff": 5, "max_locations": 1}.
            $table->json('quotas');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->string('gateway_subscription_id')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('tenant_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('feature_code', 64);
            $table->boolean('enabled');
            $table->json('params')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'feature_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_features');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
