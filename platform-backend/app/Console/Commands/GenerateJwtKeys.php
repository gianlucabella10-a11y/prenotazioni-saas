<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

/**
 * Generates the RS256 keypair for local/dev environments. In production the
 * keys come from the secrets manager (JWT_*_KEY_BASE64) and this command is
 * not used.
 */
class GenerateJwtKeys extends Command
{
    protected $signature = 'jwt:generate-keys {--force : Overwrite existing keys}';

    protected $description = 'Generate the RS256 keypair used to sign API access tokens';

    public function handle(): int
    {
        $privatePath = (string) config('jwt.private_key_path');
        $publicPath = (string) config('jwt.public_key_path');

        if (! $this->option('force') && (is_file($privatePath) || is_file($publicPath))) {
            $this->error('Keys already exist. Use --force to overwrite (existing tokens will be invalidated).');

            return self::FAILURE;
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new RuntimeException('OpenSSL failed to generate an RSA keypair.');
        }

        openssl_pkey_export($resource, $privatePem);
        $publicPem = openssl_pkey_get_details($resource)['key'];

        $dir = dirname($privatePath);

        if (! is_dir($dir) && ! mkdir($dir, 0750, true)) {
            throw new RuntimeException("Cannot create key directory {$dir}.");
        }

        file_put_contents($privatePath, $privatePem);
        chmod($privatePath, 0600);
        file_put_contents($publicPath, $publicPem);
        chmod($publicPath, 0644);

        $this->info("Keys written to {$dir}.");

        return self::SUCCESS;
    }
}
