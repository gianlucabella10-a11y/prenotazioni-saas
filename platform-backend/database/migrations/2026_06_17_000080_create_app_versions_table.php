<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Version management (FASE 4): versioni rilasciate per App Project, con note
 * di rilascio e stato (active/deprecated). Prepara il forced-update futuro
 * (l'app potrà confrontare il proprio build_number con l'ultima attiva).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('app_project_id')->constrained()->cascadeOnDelete();
            $table->string('version', 32);
            $table->unsignedInteger('build_number');
            $table->text('release_notes')->nullable();
            $table->string('status', 16)->default('active'); // active | deprecated
            $table->timestamps();
            $table->unique(['app_project_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
