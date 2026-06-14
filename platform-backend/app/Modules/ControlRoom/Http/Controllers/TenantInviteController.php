<?php

declare(strict_types=1);

namespace App\Modules\ControlRoom\Http\Controllers;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Enums\UserType;
use App\Foundation\Tenancy\CurrentTenant;
use App\Models\User;
use App\Modules\TenantManagement\Application\IssueTenantInvite;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Inviti del titolare dalla Control Room. Riusa IssueTenantInvite (stesso
 * meccanismo del provisioning); il link plaintext è mostrato una sola volta.
 */
final class TenantInviteController extends Controller
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function regenerate(Request $request, string $uuid, IssueTenantInvite $invite, AuditLogger $audit): RedirectResponse
    {
        $owner = $this->ownerFor($uuid);

        if ($owner === null) {
            return back()->with('error', 'Nessun titolare associato a questo cliente.');
        }

        $token = $invite->forUser($owner);

        $audit->log('control_room.invite_regenerated', $request->user('admin')->id, [], $owner->tenant_id);

        return back()
            ->with('status', 'Nuovo link di accesso generato (visibile solo ora).')
            ->with('invite_link', route('dashboard.invite', ['email' => $owner->email, 'token' => $token]));
    }

    public function revoke(Request $request, string $uuid, AuditLogger $audit): RedirectResponse
    {
        $owner = $this->ownerFor($uuid);

        if ($owner === null) {
            return back()->with('error', 'Nessun titolare associato a questo cliente.');
        }

        DB::table('password_reset_tokens')->where('email', $owner->email)->delete();

        $audit->log('control_room.invite_revoked', $request->user('admin')->id, [], $owner->tenant_id);

        return back()->with('status', 'Invito revocato.');
    }

    private function ownerFor(string $uuid): ?User
    {
        return $this->currentTenant->bypass(function () use ($uuid): ?User {
            $tenant = Tenant::query()->where('uuid', $uuid)->first();

            if ($tenant === null) {
                return null;
            }

            return User::query()
                ->where('tenant_id', $tenant->id)
                ->where('type', UserType::TenantAdmin->value)
                ->first();
        });
    }
}
