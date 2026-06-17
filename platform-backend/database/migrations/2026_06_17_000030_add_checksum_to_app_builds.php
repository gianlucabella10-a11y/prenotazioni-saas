<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integrità dell'artifact di build (FASE 3): checksum SHA-256 del file prodotto
 * (APK/AAB/IPA). Permette di verificare il download e di non sovrascrivere
 * artifact diversi con la stessa versione. Additivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_builds', function (Blueprint $table): void {
            $table->string('checksum', 64)->nullable()->after('artifact_path');
        });
    }

    public function down(): void
    {
        Schema::table('app_builds', function (Blueprint $table): void {
            $table->dropColumn('checksum');
        });
    }
};
