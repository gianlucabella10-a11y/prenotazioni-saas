<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feedback dei beta tester (FASE 6): segnalazioni dall'app, per-tenant.
 * Additivo, non tocca il dominio prenotazioni.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beta_feedback', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('app_version', 32)->nullable();
            $table->string('platform', 16)->nullable();
            $table->text('message');
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beta_feedback');
    }
};
