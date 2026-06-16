<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production-ready device registry (Fase 4 push): adds tenant binding and
 * diagnostic metadata to the existing `devices` table. All nullable so
 * existing registrations are unaffected; tenant_id is set on (re)register
 * to mirror the user's tenant for fast, auditable isolation queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('device_name')->nullable()->after('platform');
            $table->string('app_version', 32)->nullable()->after('device_name');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['device_name', 'app_version']);
        });
    }
};
