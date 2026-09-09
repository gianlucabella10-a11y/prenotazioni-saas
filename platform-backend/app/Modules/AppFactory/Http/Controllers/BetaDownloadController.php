<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Http\Controllers;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use App\Modules\AppFactory\Infrastructure\Models\BetaDownloadToken;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download privato dell'APK per i beta tester (FASE 5). L'accesso è protetto da
 * un TOKEN opaco con scadenza, limite download, conteggio e revoca (nessun APK
 * esposto direttamente). Serve solo build `built` con artifact reale.
 */
final class BetaDownloadController extends Controller
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function download(string $token): StreamedResponse
    {
        $artifact = $this->currentTenant->bypass(function () use ($token): ?array {
            $record = BetaDownloadToken::query()->where('token', $token)->first();

            if ($record === null || ! $record->isDownloadable()) {
                return null;
            }

            $build = AppBuild::query()
                ->where('id', $record->app_build_id)
                ->where('status', 'built')
                ->first();

            if ($build === null || $build->artifact_path === null) {
                return null;
            }

            $record->increment('download_count');

            return ['path' => $build->artifact_path, 'version' => $build->version];
        });

        abort_if($artifact === null, 404, 'Link non valido, scaduto o revocato.');

        $disk = (string) config('app_factory.artifact_disk', 'local');

        abort_unless(Storage::disk($disk)->exists($artifact['path']), 404, 'Artifact non trovato.');

        return Storage::disk($disk)->download($artifact['path'], "app-{$artifact['version']}.apk");
    }
}
