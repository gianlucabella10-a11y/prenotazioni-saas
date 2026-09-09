<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asset Factory (FASE 2A): i derivati generati dal logo (icone/splash) sono
 * righe brand_assets con `variant` (spec, es. android_xxhdpi/ios_180) e
 * `version` (rigenerazione). Il master (kind=logo/icon_source) ha variant null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_assets', function (Blueprint $table): void {
            $table->string('variant', 64)->nullable()->after('kind');
            $table->unsignedInteger('version')->default(1)->after('variant');
        });
    }

    public function down(): void
    {
        Schema::table('brand_assets', function (Blueprint $table): void {
            $table->dropColumn(['variant', 'version']);
        });
    }
};
