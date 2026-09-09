<?php

declare(strict_types=1);

namespace Tests\Feature\ControlRoom;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Vista di sola lettura sul log applicativo (FOUNDER_EXPERIENCE_AUDIT.md) —
 * elimina l'ultima diagnosi che richiedeva ancora accesso diretto al
 * filesystem del server. Il file reale può essere grande (decine di MB su
 * una macchina di sviluppo con molte esecuzioni di test): il backup/ripristino
 * qui usa `rename()`, mai una lettura completa in memoria (a differenza del
 * controller sotto test, che legge solo l'ultimo blocco — coerente).
 */
final class LogViewerTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    private function actingSuperAdmin(): User
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_log_viewer_shows_recent_lines(): void
    {
        $this->actingSuperAdmin();

        $path = storage_path('logs/laravel.log');
        $backup = $path.'.test-backup';
        $hadOriginal = is_file($path);

        if ($hadOriginal) {
            rename($path, $backup);
        }

        file_put_contents($path, "riga vecchia\n[2026-07-20 10:00:00] local.ERROR: qualcosa è andato storto CONTROL_ROOM_MARKER\n");

        try {
            $this->get('/control-room/logs')
                ->assertOk()
                ->assertSee('CONTROL_ROOM_MARKER');
        } finally {
            unlink($path);

            if ($hadOriginal) {
                rename($backup, $path);
            }
        }
    }

    public function test_log_viewer_handles_missing_file(): void
    {
        $this->actingSuperAdmin();

        $path = storage_path('logs/laravel.log');
        $backup = $path.'.test-backup';
        $hadOriginal = is_file($path);

        if ($hadOriginal) {
            rename($path, $backup);
        }

        try {
            $this->get('/control-room/logs')
                ->assertOk()
                ->assertSee('Nessun file di log trovato');
        } finally {
            if ($hadOriginal) {
                rename($backup, $path);
            }
        }
    }
}
