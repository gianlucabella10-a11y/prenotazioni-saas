<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business contacts & social for the premium client "scheda attività":
 * brand-level (one per tenant), all nullable so existing tenants are
 * unaffected. Per-location address/phone stay on `locations`. Exposed at
 * runtime via GET /app/config (BuildWhiteLabelConfig) — white-label safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_profiles', function (Blueprint $table): void {
            $table->string('contact_email')->nullable()->after('support_url');
            $table->string('website_url')->nullable()->after('contact_email');
            $table->string('whatsapp_number', 32)->nullable()->after('website_url');
            $table->string('whatsapp_message', 255)->nullable()->after('whatsapp_number');
            $table->string('instagram_url')->nullable()->after('whatsapp_message');
            $table->string('facebook_url')->nullable()->after('instagram_url');
            $table->string('maps_url')->nullable()->after('facebook_url');
        });
    }

    public function down(): void
    {
        Schema::table('brand_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'contact_email',
                'website_url',
                'whatsapp_number',
                'whatsapp_message',
                'instagram_url',
                'facebook_url',
                'maps_url',
            ]);
        });
    }
};
