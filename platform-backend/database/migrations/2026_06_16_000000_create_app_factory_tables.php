<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App Factory (FASE 1): per-tenant store/build identity (`app_projects`,
 * 1:1 con il tenant) e storico generazioni (`app_builds`). Additivo: il
 * brand resta in brand_profiles, il catalogo nei moduli esistenti. Le
 * identità (bundle_id/package_name) sono uniche e immutabili dopo la prima
 * pubblicazione (docs/27 §1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_projects', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('shortcode', 16)->unique();
            $table->string('store_name', 30);
            $table->string('bundle_id')->unique();
            $table->string('package_name')->unique();
            $table->string('template_code', 32)->default('default');
            $table->string('font_style', 32)->nullable();
            $table->boolean('powered_by_enabled')->default(true);
            $table->string('build_status', 20)->default('draft');
            $table->timestamp('last_generated_at')->nullable();
            $table->json('build_manifest')->nullable();
            $table->timestamps();
        });

        Schema::create('app_builds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('app_project_id')->constrained()->cascadeOnDelete();
            $table->string('version', 32);
            $table->string('platform', 16); // config | android | ios
            $table->string('status', 20);   // generated | built | published | failed
            $table->string('artifact_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['app_project_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_builds');
        Schema::dropIfExists('app_projects');
    }
};
