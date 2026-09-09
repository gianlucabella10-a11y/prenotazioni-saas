<?php

declare(strict_types=1);

namespace Tests\Feature\ControlRoom;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;
use ZipArchive;

/**
 * Backup del centro operativo dalla Control Room — elimina l'unica
 * operazione "backup" che oggi richiedeva il terminale
 * (ZERO_MANUAL_OPERATIONS_AUDIT.md). I file reali creati durante il test
 * vengono ripuliti in tearDown per non lasciare residui nel repository.
 */
final class BackupTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshDatabase;

    /** @var list<string> */
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    private function actingSuperAdmin(): User
    {
        $admin = $this->bypassTenancy(fn (): User => User::factory()->superAdmin()->create());
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_backup_page_renders(): void
    {
        $this->actingSuperAdmin();

        $this->get('/control-room/backup')
            ->assertOk()
            ->assertSee('Backup ora');
    }

    public function test_creating_a_backup_produces_a_valid_zip(): void
    {
        $this->actingSuperAdmin();

        $response = $this->post('/control-room/backup');
        $response->assertRedirect(route('control.backup.index'));
        $response->assertSessionHas('status');

        $backupsDir = base_path('../backups');
        $files = glob($backupsDir.'/backup-*.zip') ?: [];
        self::assertNotEmpty($files, 'Il backup dovrebbe aver creato almeno un file zip.');

        $latest = $files[count($files) - 1];
        $this->createdFiles[] = $latest;

        $zip = new ZipArchive;
        self::assertTrue($zip->open($latest) === true, 'Il file di backup deve essere un archivio zip valido.');
        // In ambiente di test DB_DATABASE=":memory:" (phpunit.xml): non è un file reale,
        // quindi il database è correttamente escluso — stesso comportamento onesto dello
        // script bash per le connessioni non basate su file (vedi CreatePlatformBackup).
        self::assertFalse($zip->locateName('database.sqlite'), 'In test la connessione è :memory:, nessun file DB da includere.');
        $zip->close();

        $this->get('/control-room/backup')->assertOk()->assertSee(basename($latest));
    }

    public function test_old_backups_beyond_retention_are_pruned(): void
    {
        $this->actingSuperAdmin();

        $backupsDir = base_path('../backups');

        if (! is_dir($backupsDir)) {
            mkdir($backupsDir, 0755, true);
        }

        // Crea 15 backup finti più vecchi del limite di retention (14) con
        // mtime scaglionati, per verificare che vengano davvero i più vecchi
        // a essere rimossi, non un sottoinsieme arbitrario.
        for ($i = 0; $i < 15; $i++) {
            $fake = "{$backupsDir}/backup-fake-{$i}.zip";
            file_put_contents($fake, 'x');
            touch($fake, time() - (1000 - $i));
            $this->createdFiles[] = $fake;
        }

        $response = $this->post('/control-room/backup');
        $response->assertRedirect(route('control.backup.index'));
        $response->assertSessionHas('status');
        self::assertStringContainsString('retention automatica', (string) session('status'));

        $remaining = glob($backupsDir.'/backup-*.zip') ?: [];
        $this->createdFiles = array_merge($this->createdFiles, $remaining);

        // 15 finti + 1 reale appena creato = 16, retention tiene solo gli
        // ultimi 14 (i più recenti per mtime) → 2 eliminati.
        self::assertCount(14, $remaining, 'Devono restare solo gli ultimi 14 backup dopo la retention.');

        $survivingFakeIndexes = [];
        foreach ($remaining as $file) {
            if (preg_match('/backup-fake-(\d+)\.zip$/', $file, $m) === 1) {
                $survivingFakeIndexes[] = (int) $m[1];
            }
        }
        // I due finti più vecchi (indice 0 e 1, mtime più basso) devono essere stati rimossi.
        self::assertNotContains(0, $survivingFakeIndexes);
        self::assertNotContains(1, $survivingFakeIndexes);
    }
}
