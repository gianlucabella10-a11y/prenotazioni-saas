<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timeline della build (osservabilità): quando è stata accodata, avviata e
 * conclusa. Permette di misurare durata e diagnosticare i job. Additivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_builds', function (Blueprint $table): void {
            $table->timestamp('queued_at')->nullable()->after('error_message');
            $table->timestamp('started_at')->nullable()->after('queued_at');
            $table->timestamp('finished_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('app_builds', function (Blueprint $table): void {
            $table->dropColumn(['queued_at', 'started_at', 'finished_at']);
        });
    }
};
