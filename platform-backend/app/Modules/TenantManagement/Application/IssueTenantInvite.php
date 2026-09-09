<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application;

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;

/**
 * Emette un invito monouso per impostare la password al primo accesso.
 * Il token è salvato in `password_reset_tokens` come HASH (mai in chiaro a
 * DB); il plaintext è restituito UNA sola volta al chiamante (mostrato in
 * Control Room / inviato per email) e non è più ripescabile.
 *
 * Punto unico di verità: usato dal provisioning (ProvisionTenant) e dalla
 * rigenerazione invito della Control Room — nessuna logica duplicata.
 */
final readonly class IssueTenantInvite
{
    public function __construct(private Connection $db) {}

    public function forUser(User $user): string
    {
        $token = Str::random(64);

        $this->db->table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => hash('sha256', $token), 'created_at' => now()],
        );

        return $token;
    }
}
