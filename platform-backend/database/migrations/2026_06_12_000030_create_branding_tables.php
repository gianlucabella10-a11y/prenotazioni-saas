<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * White label branding: one brand profile per tenant. `config_version` is
 * bumped on every change and drives client-side cache busting (ETag) of the
 * runtime WhiteLabelConfig.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->restrictOnDelete();
            $table->string('app_name', 30);
            $table->string('tagline', 80)->nullable();
            $table->char('primary_color', 7);
            $table->char('secondary_color', 7);
            // Full design-system token set consumed by the Flutter theme.
            $table->json('theme');
            $table->unsignedInteger('config_version')->default(1);
            $table->boolean('contrast_validated')->default(false);
            $table->timestamps();
        });

        Schema::create('brand_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_profile_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('disk_path');
            $table->string('mime', 64)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->timestamps();
            $table->index(['brand_profile_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_assets');
        Schema::dropIfExists('brand_profiles');
    }
};
