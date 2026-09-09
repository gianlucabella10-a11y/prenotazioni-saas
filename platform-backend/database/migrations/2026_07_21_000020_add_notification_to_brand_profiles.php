<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push Notifications (Fase 7): stile per-tenant della notifica in un JSON
 * `notification` (una colonna, estendibile). Contiene i due parametri sicuri e
 * a effetto immediato — colore accent (default = primary del brand) e priorità.
 * Nullable: assente → default piattaforma. Runtime (nessuna build): la prossima
 * push usa i nuovi valori. Icona/canale/suono custom sono build-time e restano
 * fuori per non sopprimere la consegna su Android O+.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_profiles', function (Blueprint $table): void {
            $table->json('notification')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('brand_profiles', function (Blueprint $table): void {
            $table->dropColumn('notification');
        });
    }
};
