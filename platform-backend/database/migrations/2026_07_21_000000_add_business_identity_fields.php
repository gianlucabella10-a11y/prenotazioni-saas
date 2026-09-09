<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business Identity (Fase 3): campi anagrafici/social mancanti. Brand-level
 * (uno per tenant, tutti nullable): TikTok, Cookie policy, P.IVA. Le coordinate
 * GPS stanno sulla sede (`locations`) accanto all'indirizzo e rendono più
 * preciso il link a Google Maps in GET /app/config. Additivo: i tenant
 * esistenti non sono toccati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_profiles', function (Blueprint $table): void {
            $table->string('tiktok_url')->nullable()->after('facebook_url');
            $table->string('cookie_url')->nullable()->after('terms_url');
            $table->string('vat_number', 32)->nullable()->after('cookie_url');
        });

        Schema::table('locations', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('brand_profiles', function (Blueprint $table): void {
            $table->dropColumn(['tiktok_url', 'cookie_url', 'vat_number']);
        });

        Schema::table('locations', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
