<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Application\Dispatchers;

use App\Modules\AppFactory\Application\BuildDispatcher;
use App\Modules\AppFactory\Application\BuildDispatchResult;
use App\Modules\AppFactory\Application\BuildFailedException;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Driver `local`: compila DAVVERO l'app sulla macchina corrente eseguendo la
 * pipeline reale `flutter pub get → analyze → test → build apk --release` in
 * modo parametrico (identità + dart-define dal manifest del tenant). Copia
 * l'artifact in `builds/{tenant}/{version}/` con checksum SHA-256 e ritorna
 * comando/log/exit code/durata. **NON è un mock**: richiede l'Android SDK; se
 * un passo fallisce, lancia BuildFailedException con il log reale (il worker
 * marca `failed` e salva l'osservabilità). iOS richiede macOS/Xcode.
 */
final class LocalBuildDispatcher implements BuildDispatcher
{
    public function __construct(private readonly Config $config) {}

    public function dispatch(AppProject $project, string $platform): BuildDispatchResult
    {
        if ($platform !== 'android') {
            throw new RuntimeException('Il driver locale supporta solo Android (iOS richiede macOS/Xcode).');
        }

        $manifest = $this->manifest($project);
        $identity = (array) ($manifest['app_identity'] ?? []);
        $dartDefine = (array) ($manifest['runtime']['dart_define'] ?? []);
        $version = (string) ($manifest['metadata']['version'] ?? '1.0.0+1');

        $appDir = (string) $this->config->get('app_factory.flutter_app_dir');

        if (! is_dir($appDir)) {
            throw new RuntimeException("Cartella app Flutter non trovata: {$appDir} (APP_FACTORY_FLUTTER_APP_DIR).");
        }

        $buildCommand = array_merge(
            ['flutter', 'build', 'apk', '--release',
                '-PAPP_ID='.($identity['package_name'] ?? 'com.platform.client_app'),
                '-PAPP_NAME='.($identity['name'] ?? $identity['store_name'] ?? 'App')],
            array_map(static fn ($k, $v): string => "--dart-define={$k}={$v}", array_keys($dartDefine), array_values($dartDefine)),
        );

        // Pipeline reale: clean + deps + validazione + test + build (mai simulato).
        $steps = [
            ['flutter', 'clean'],
            ['flutter', 'pub', 'get'],
            ['flutter', 'analyze'],
            ['flutter', 'test'],
            $buildCommand,
        ];

        $start = microtime(true);
        $log = '';

        foreach ($steps as $step) {
            $label = implode(' ', $step);
            $process = new Process($step, $appDir, timeout: (float) $this->config->get('app_factory.build_timeout', 1800));
            $process->run();
            $log .= "$ {$label}\n".$process->getOutput().$process->getErrorOutput()."\n";

            if (! $process->isSuccessful()) {
                throw new BuildFailedException(
                    "Step fallito: {$label} (exit {$process->getExitCode()})",
                    $label,
                    mb_substr($log, -8000),
                    (int) $process->getExitCode(),
                    (int) round((microtime(true) - $start) * 1000),
                );
            }
        }

        $apk = "{$appDir}/build/app/outputs/flutter-apk/app-release.apk";

        if (! is_file($apk)) {
            throw new BuildFailedException('APK non trovato dopo la build: '.$apk, implode(' ', $buildCommand), mb_substr($log, -8000), 0, (int) round((microtime(true) - $start) * 1000));
        }

        $disk = (string) $this->config->get('app_factory.artifact_disk', 'local');
        $relative = "builds/{$project->tenant_id}/{$version}/app-release.apk";
        Storage::disk($disk)->put($relative, (string) file_get_contents($apk));

        return BuildDispatchResult::built(
            "local://{$relative}",
            $relative,
            (string) hash_file('sha256', $apk),
            implode(' ', $buildCommand),
            mb_substr($log, -8000),
            0,
            (int) round((microtime(true) - $start) * 1000),
            (int) (filesize($apk) ?: 0),
        );
    }

    public function name(): string
    {
        return 'local';
    }

    /** @return array<string, mixed> */
    private function manifest(AppProject $project): array
    {
        $disk = (string) $this->config->get('app_factory.manifest_disk', 'local');
        $path = "app_factory/{$project->uuid}/manifest-latest.json";

        if (! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException('Manifest assente: genera il pacchetto prima della build.');
        }

        return (array) json_decode((string) Storage::disk($disk)->get($path), true);
    }
}
