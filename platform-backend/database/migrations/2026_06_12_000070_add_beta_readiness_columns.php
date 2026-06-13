<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP Stabilization (beta readiness) — additive only (docs/32 §6):
 *
 *  - email verification state + single-use verification codes (fix S1,
 *    MVP_PRODUCTION_READINESS_REPORT §6: CRM linking deferred to the moment
 *    email ownership is PROVEN)
 *  - identity fields on users (customer record is created only after
 *    verification, so registration data must live on the user)
 *  - consent document versioning (GDPR foundation, Fase 3)
 *  - tenant-configurable legal URLs (white label, store compliance)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('first_name', 100)->nullable()->after('email_verified_at');
            $table->string('last_name', 100)->nullable()->after('first_name');
        });

        Schema::create('email_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // SHA-256 of (code + user uuid); the clear code is never stored.
            $table->char('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::table('consents', function (Blueprint $table): void {
            $table->string('document_version', 32)->nullable()->after('source');
            // Privacy consent is captured AT REGISTRATION, before any
            // customer record exists (the customer is created only after
            // email verification): consents can reference the user directly.
            $table->foreignId('user_id')->nullable()->after('customer_id')
                ->constrained()->nullOnDelete();
        });

        // customer_id becomes optional for user-level consents.
        Schema::table('consents', function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_id')->nullable()->change();
        });

        Schema::table('brand_profiles', function (Blueprint $table): void {
            $table->string('privacy_policy_url')->nullable()->after('contrast_validated');
            $table->string('terms_url')->nullable()->after('privacy_policy_url');
            $table->string('support_url')->nullable()->after('terms_url');
        });
    }

    public function down(): void
    {
        Schema::table('brand_profiles', function (Blueprint $table): void {
            $table->dropColumn(['privacy_policy_url', 'terms_url', 'support_url']);
        });

        Schema::table('consents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('document_version');
        });

        Schema::dropIfExists('email_verifications');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['email_verified_at', 'first_name', 'last_name']);
        });
    }
};
