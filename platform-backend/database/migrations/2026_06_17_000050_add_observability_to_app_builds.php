<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Osservabilità della build (FASE 1): log completo, comando eseguito, exit code
 * e durata. Consultabili dalla Control Room senza accedere ai log del server.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_builds', function (Blueprint $table): void {
            $table->text('command')->nullable()->after('error_message');
            $table->longText('build_log')->nullable()->after('command');
            $table->integer('exit_code')->nullable()->after('build_log');
            $table->unsignedInteger('duration_ms')->nullable()->after('exit_code');
        });
    }

    public function down(): void
    {
        Schema::table('app_builds', function (Blueprint $table): void {
            $table->dropColumn(['command', 'build_log', 'exit_code', 'duration_ms']);
        });
    }
};
