<?php

declare(strict_types=1);

namespace App\Modules\Branding\Application;

use App\Modules\Branding\Infrastructure\Models\BrandAsset;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Salva il logo del tenant sul disco asset e registra/aggiorna la riga
 * `brand_assets` (kind=logo), poi incrementa `config_version` per il
 * cache-busting del client (ETag). Riusato dalla Control Room (quick setup).
 * La tabella `brand_assets` esiste già: qui se ne collega solo l'upload.
 */
final readonly class StoreBrandLogo
{
    public function store(BrandProfile $brand, UploadedFile $file): BrandAsset
    {
        $disk = (string) config('branding.asset_disk', 'public');
        $extension = $file->extension() ?: 'png';
        $path = "brand/{$brand->tenant_id}/logo.{$extension}";
        $contents = $file->getContent();

        Storage::disk($disk)->put($path, $contents);

        $asset = BrandAsset::query()->updateOrCreate(
            ['brand_profile_id' => $brand->id, 'kind' => BrandAsset::KIND_LOGO],
            [
                'disk_path' => $path,
                'mime' => $file->getMimeType(),
                'checksum' => hash('sha256', $contents),
            ],
        );

        $brand->forceFill(['config_version' => $brand->config_version + 1])->save();

        return $asset;
    }
}
