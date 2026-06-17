<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dimensione dell'artifact prodotto (FASE 1/4): mostrata in Control Room
 * ("BUILD COMPLETED · size"). Additivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_builds', function (Blueprint $table): void {
            $table->unsignedBigInteger('size_bytes')->nullable()->after('checksum');
        });
    }

    public function down(): void
    {
        Schema::table('app_builds', function (Blueprint $table): void {
            $table->dropColumn('size_bytes');
        });
    }
};
