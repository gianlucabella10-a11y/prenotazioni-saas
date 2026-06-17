<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Build engine 10/10: traccia il motivo di un fallimento di build/pubblicazione
 * (riportato dalla CI via app:build-record) per mostrarlo nello storico della
 * Control Room. Additivo, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_builds', function (Blueprint $table): void {
            $table->text('error_message')->nullable()->after('artifact_path');
        });
    }

    public function down(): void
    {
        Schema::table('app_builds', function (Blueprint $table): void {
            $table->dropColumn('error_message');
        });
    }
};
