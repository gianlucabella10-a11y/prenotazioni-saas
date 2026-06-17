<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token di download beta (FASE 5): link privato per scaricare l'APK, con
 * scadenza, limite download, conteggio e revoca. Sostituisce l'URL firmato
 * stateless (che non permette conteggio/revoca). Additivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beta_download_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('app_build_id')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->unsignedInteger('max_downloads')->nullable(); // null = illimitato
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['app_build_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beta_download_tokens');
    }
};
