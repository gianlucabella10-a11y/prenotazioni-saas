<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Auth\Totp;
use App\Foundation\Enums\UserType;
use App\Models\MfaCredential;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Autenticazione web della dashboard (sessione + CSRF, docs/26 review #26).
 *
 * Flussi:
 *  - login email+password → (MFA verify | MFA setup obbligatorio per
 *    l'owner, docs/14 §2) → sessione
 *  - accettazione invito: il provisioning consegna un invite_token
 *    (hash in password_reset_tokens); qui l'owner imposta la password
 *  - logout con invalidazione sessione
 *
 * Limite MVP documentato (PLAN §6): senza sottodomini, un'email staff
 * presente su più tenant non può accedere dal web.
 */
final class WebAuthController extends Controller
{
    private const MFA_SESSION_KEY = 'dashboard.mfa_user_id';

    public function showLogin(): View
    {
        return view('dashboard.auth.login');
    }

    public function login(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $candidates = User::query()
            ->where('email', mb_strtolower(trim($data['email'])))
            ->whereIn('type', [UserType::TenantAdmin->value, UserType::Staff->value])
            ->where('status', 'active')
            ->get();

        if ($candidates->count() > 1) {
            return back()->withErrors([
                'email' => 'Questa email è registrata su più attività: contatta il supporto.',
            ]);
        }

        $user = $candidates->first();

        if ($user === null
            || $user->password === null
            || ! Hash::check($data['password'], $user->password)
        ) {
            $audit->log('dashboard.login_failed', null, [], $user?->tenant_id);

            return back()->withErrors(['email' => 'Credenziali non valide.'])
                ->onlyInput('email');
        }

        if ($user->requiresMfaVerification() || $user->requiresMfaSetup()) {
            $request->session()->put(self::MFA_SESSION_KEY, $user->id);

            return $user->requiresMfaSetup()
                ? redirect()->route('dashboard.mfa.setup')
                : redirect()->route('dashboard.mfa.challenge');
        }

        return $this->establishSession($request, $user, $audit);
    }

    public function showMfaChallenge(Request $request): View|RedirectResponse
    {
        return $this->pendingMfaUser($request) === null
            ? redirect()->route('dashboard.login')
            : view('dashboard.auth.mfa-challenge');
    }

    public function verifyMfa(Request $request, Totp $totp, AuditLogger $audit): RedirectResponse
    {
        $user = $this->pendingMfaUser($request);

        if ($user === null) {
            return redirect()->route('dashboard.login');
        }

        $data = $request->validate(['code' => ['required', 'digits:6']]);

        $credential = $user->confirmedMfaCredential();

        if ($credential === null || ! $totp->verify($credential->secret, $data['code'])) {
            $audit->log('dashboard.mfa_failed', $user->id, [], $user->tenant_id);

            return back()->withErrors(['code' => 'Codice non valido.']);
        }

        return $this->establishSession($request, $user, $audit);
    }

    /**
     * Primo accesso dell'owner (MFA obbligatoria, docs/14 §2): genera il
     * secret e lo mostra come otpauth-URI + stringa copiabile.
     */
    public function showMfaSetup(Request $request, Totp $totp): View|RedirectResponse
    {
        $user = $this->pendingMfaUser($request);

        if ($user === null) {
            return redirect()->route('dashboard.login');
        }

        $credential = $user->mfaCredentials()
            ->firstOrCreate(
                ['type' => MfaCredential::TYPE_TOTP],
                ['secret' => $totp->generateSecret()],
            );

        return view('dashboard.auth.mfa-setup', [
            'secret' => $credential->secret,
            'otpauthUri' => $totp->provisioningUri(
                $credential->secret,
                $user->email ?? $user->uuid,
                config('app.name'),
            ),
        ]);
    }

    public function confirmMfaSetup(Request $request, Totp $totp, AuditLogger $audit): RedirectResponse
    {
        $user = $this->pendingMfaUser($request);

        if ($user === null) {
            return redirect()->route('dashboard.login');
        }

        $data = $request->validate(['code' => ['required', 'digits:6']]);

        $credential = $user->mfaCredentials()
            ->where('type', MfaCredential::TYPE_TOTP)
            ->first();

        if ($credential === null || ! $totp->verify($credential->secret, $data['code'])) {
            return back()->withErrors(['code' => 'Codice non valido: riprova dalla tua app authenticator.']);
        }

        $credential->forceFill(['confirmed_at' => now()])->save();
        $audit->log('dashboard.mfa_enrolled', $user->id, [], $user->tenant_id);

        return $this->establishSession($request, $user, $audit);
    }

    public function showInvite(Request $request): View
    {
        return view('dashboard.auth.invite', [
            'email' => (string) $request->query('email', ''),
            'token' => (string) $request->query('token', ''),
        ]);
    }

    /** L'invito del provisioning diventa la password del titolare. */
    public function acceptInvite(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $email = mb_strtolower(trim($data['email']));

        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        $tokenValid = $row !== null
            && hash_equals($row->token, hash('sha256', $data['token']))
            && now()->diffInHours($row->created_at, true) < 72;

        if (! $tokenValid) {
            return back()->withErrors(['token' => 'Invito non valido o scaduto: contatta il supporto.'])
                ->onlyInput('email');
        }

        $user = User::query()
            ->where('email', $email)
            ->whereIn('type', [UserType::TenantAdmin->value, UserType::Staff->value])
            ->where('status', 'active')
            ->first();

        if ($user === null) {
            return back()->withErrors(['token' => 'Account non trovato: contatta il supporto.']);
        }

        $user->forceFill(['password' => $data['password']])->save(); // hashed cast
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        $audit->log('dashboard.invite_accepted', $user->id, [], $user->tenant_id);

        return redirect()->route('dashboard.login')
            ->with('status', 'Password impostata: ora puoi accedere.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('dashboard.login');
    }

    private function establishSession(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $request->session()->forget(self::MFA_SESSION_KEY);

        Auth::guard('web')->login($user);
        $request->session()->regenerate(); // anti session-fixation

        $user->forceFill(['last_login_at' => now()])->save();
        $audit->log('dashboard.login', $user->id, [], $user->tenant_id);

        return redirect()->route('dashboard.home');
    }

    private function pendingMfaUser(Request $request): ?User
    {
        $id = $request->session()->get(self::MFA_SESSION_KEY);

        return $id === null ? null : User::query()->find($id);
    }
}
