<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App Factory (FASE 3 — scala): traccia la versione del core con cui l'app del
 * tenant è stata pubblicata. Permette il "release train": quando il core viene
 * aggiornato, le app con `built_core_version` precedente risultano *stale* e
 * vanno ricostruite (a lotti / canary). Additivo, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_projects', function (Blueprint $table): void {
            $table->string('built_core_version', 32)->nullable()->after('build_status');
        });
    }

    public function down(): void
    {
        Schema::table('app_projects', function (Blueprint $table): void {
            $table->dropColumn('built_core_version');
        });
    }
};
