<?php

declare(strict_types=1);

namespace App\Modules\Branding\Application;

use App\Modules\Branding\Infrastructure\Models\BrandAsset;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Salva il logo del tenant sul disco asset e registra/aggiorna la riga
 * `brand_assets` (kind=logo), poi incrementa `config_version` per il
 * cache-busting del client (ETag). Riusato dalla Control Room (quick setup).
 * La tabella `brand_assets` esiste già: qui se ne collega solo l'upload.
 *
 * Valida il master (raster, formato, lato minimo) PRIMA di salvare: è la
 * sorgente da cui l'Asset Factory deriva icone/splash, quindi un input scadente
 * va respinto qui — niente logica di validazione nei controller.
 */
final readonly class StoreBrandLogo
{
    /** MIME raster accettati per il master. */
    private const ALLOWED_MIME = ['image/png', 'image/jpeg', 'image/webp'];

    /** Lato minimo per derivare un set icone/splash dignitoso. */
    private const MIN_SIDE = 256;

    public function store(BrandProfile $brand, UploadedFile $file): BrandAsset
    {
        $contents = $file->getContent();
        $this->validate($contents);

        $disk = (string) config('branding.asset_disk', 'public');
        $extension = $file->extension() ?: 'png';
        $path = "brand/{$brand->tenant_id}/logo.{$extension}";

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

    /** @throws ValidationException se non è un raster valido o è troppo piccolo */
    private function validate(string $contents): void
    {
        $info = @getimagesizefromstring($contents);

        if ($info === false) {
            throw ValidationException::withMessages([
                'logo' => 'Il logo deve essere un\'immagine valida (PNG, JPG o WebP).',
            ]);
        }

        if (! in_array($info['mime'] ?? '', self::ALLOWED_MIME, true)) {
            throw ValidationException::withMessages([
                'logo' => 'Formato non supportato: usa PNG, JPG o WebP.',
            ]);
        }

        if ($info[0] < self::MIN_SIDE || $info[1] < self::MIN_SIDE) {
            throw ValidationException::withMessages([
                'logo' => 'Il logo è troppo piccolo: minimo '.self::MIN_SIDE.'×'.self::MIN_SIDE.' px (consigliato 1024×1024).',
            ]);
        }
    }
}
