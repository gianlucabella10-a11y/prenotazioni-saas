<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer Experience (Fase 5): testi e immagini editoriali dell'app in un
 * unico JSON `content` (una colonna, estendibile, niente decine di campi).
 * Nullable: assente → i default della piattaforma (config/branding.php)
 * riproducono le copy attuali, quindi nessuna regressione. Esposto a runtime
 * via GET /app/config e bumpato con config_version — nessuna nuova build.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_profiles', function (Blueprint $table): void {
            $table->json('content')->nullable()->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('brand_profiles', function (Blueprint $table): void {
            $table->dropColumn('content');
        });
    }
};
