<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roster dei beta tester per-tenant (FASE 4): chi è invitato/attivo/bloccato.
 * Additivo, gestito dalla Control Room. Non tocca auth/customers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beta_testers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email');
            $table->string('device', 120)->nullable();
            $table->string('status', 16)->default('invited'); // invited | active | blocked
            $table->timestamps();
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beta_testers');
    }
};
