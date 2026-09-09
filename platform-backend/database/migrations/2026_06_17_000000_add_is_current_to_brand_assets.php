<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asset Factory 10/10: storico versioni dei derivati senza cancellazione.
 * `is_current` marca la versione attiva (usata da manifest/preview); le
 * precedenti restano per storico e rollback. Additivo, default true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_assets', function (Blueprint $table): void {
            $table->boolean('is_current')->default(true)->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('brand_assets', function (Blueprint $table): void {
            $table->dropColumn('is_current');
        });
    }
};
