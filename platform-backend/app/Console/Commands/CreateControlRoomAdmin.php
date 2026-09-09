<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Foundation\Enums\UserType;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Bootstrap del primo super-admin in produzione (niente tinker). MFA
 * obbligatoria: al primo accesso su /control-room/login l'account dovrà
 * configurare la verifica in due passaggi.
 */
final class CreateControlRoomAdmin extends Command
{
    protected $signature = 'control-room:create-admin {email} {--password=}';

    protected $description = 'Crea o aggiorna un account super-admin per la Control Room.';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email non valida.');

            return self::FAILURE;
        }

        $generated = ! $this->option('password');
        $password = (string) ($this->option('password') ?: Str::password(16));

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'type' => UserType::SuperAdmin,
                'tenant_id' => null,
                'status' => 'active',
                'password' => $password, // hashed cast
                'mfa_enforced' => true,
                'email_verified_at' => now(),
                'locale' => 'it',
            ],
        );

        $this->info("Super-admin pronto: {$user->email}");

        if ($generated) {
            $this->warn("Password generata: {$password}");
            $this->line('Salvala ora: non verrà più mostrata.');
        }

        $this->line('Al primo accesso su /control-room/login dovrai configurare la verifica in due passaggi (MFA).');

        return self::SUCCESS;
    }
}
