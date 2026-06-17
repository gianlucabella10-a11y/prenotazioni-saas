<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Http\Controllers;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Infrastructure\Models\AppBuild;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download privato dell'APK per i beta tester. L'accesso è protetto da URL
 * FIRMATO con scadenza (middleware `signed`): nessun login, ma il link è
 * a prova di manomissione e scade. Serve solo build `built` con artifact reale.
 */
final class BetaDownloadController extends Controller
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function download(string $build): StreamedResponse
    {
        $artifact = $this->currentTenant->bypass(function () use ($build): ?array {
            $row = AppBuild::query()->where('uuid', $build)->where('status', 'built')->first();

            return $row === null ? null : ['path' => $row->artifact_path, 'version' => $row->version];
        });

        abort_if($artifact === null || $artifact['path'] === null, 404, 'Build non disponibile.');

        $disk = (string) config('app_factory.artifact_disk', 'local');

        abort_unless(Storage::disk($disk)->exists($artifact['path']), 404, 'Artifact non trovato.');

        return Storage::disk($disk)->download($artifact['path'], "app-{$artifact['version']}.apk");
    }
}
