<?php

declare(strict_types=1);

namespace App\Modules\ControlRoom\Http\Controllers;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Auth\Totp;
use App\Foundation\Enums\UserType;
use App\Models\MfaCredential;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Autenticazione della Control Room (guard `admin`, sessione separata +
 * CSRF). Accetta ESCLUSIVAMENTE utenti super_admin: un titolare o uno staff
 * non può autenticarsi qui. MFA obbligatoria (riuso di Totp/MfaCredential,
 * stesso schema del titolare dashboard).
 */
final class ControlRoomAuthController extends Controller
{
    private const MFA_SESSION_KEY = 'control_room.mfa_user_id';

    public function showLogin(): View
    {
        return view('control_room.auth.login');
    }

    public function login(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', mb_strtolower(trim($data['email'])))
            ->where('type', UserType::SuperAdmin->value)
            ->where('status', 'active')
            ->first();

        if ($user === null
            || $user->password === null
            || ! Hash::check($data['password'], $user->password)
        ) {
            $audit->log('control_room.login_failed');

            return back()->withErrors(['email' => 'Credenziali non valide.'])->onlyInput('email');
        }

        if ($user->requiresMfaVerification() || $user->requiresMfaSetup()) {
            $request->session()->put(self::MFA_SESSION_KEY, $user->id);

            return $user->requiresMfaSetup()
                ? redirect()->route('control.mfa.setup')
                : redirect()->route('control.mfa.challenge');
        }

        return $this->establishSession($request, $user, $audit);
    }

    public function showMfaChallenge(Request $request): View|RedirectResponse
    {
        return $this->pendingUser($request) === null
            ? redirect()->route('control.login')
            : view('control_room.auth.mfa-challenge');
    }

    public function verifyMfa(Request $request, Totp $totp, AuditLogger $audit): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('control.login');
        }

        $data = $request->validate(['code' => ['required', 'digits:6']]);

        $credential = $user->confirmedMfaCredential();

        if ($credential === null || ! $totp->verify($credential->secret, $data['code'])) {
            $audit->log('control_room.mfa_failed', $user->id);

            return back()->withErrors(['code' => 'Codice non valido.']);
        }

        return $this->establishSession($request, $user, $audit);
    }

    public function showMfaSetup(Request $request, Totp $totp): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('control.login');
        }

        $credential = $user->mfaCredentials()->firstOrCreate(
            ['type' => MfaCredential::TYPE_TOTP],
            ['secret' => $totp->generateSecret()],
        );

        return view('control_room.auth.mfa-setup', [
            'secret' => $credential->secret,
            'otpauthUri' => $totp->provisioningUri(
                $credential->secret,
                $user->email ?? $user->uuid,
                config('app.name').' Control Room',
            ),
        ]);
    }

    public function confirmMfaSetup(Request $request, Totp $totp, AuditLogger $audit): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('control.login');
        }

        $data = $request->validate(['code' => ['required', 'digits:6']]);

        $credential = $user->mfaCredentials()->where('type', MfaCredential::TYPE_TOTP)->first();

        if ($credential === null || ! $totp->verify($credential->secret, $data['code'])) {
            return back()->withErrors(['code' => 'Codice non valido: riprova dalla tua app authenticator.']);
        }

        $credential->forceFill(['confirmed_at' => now()])->save();
        $audit->log('control_room.mfa_enrolled', $user->id);

        return $this->establishSession($request, $user, $audit);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('control.login');
    }

    private function establishSession(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $request->session()->forget(self::MFA_SESSION_KEY);

        Auth::guard('admin')->login($user);
        $request->session()->regenerate(); // anti session-fixation

        $user->forceFill(['last_login_at' => now()])->save();
        $audit->log('control_room.login', $user->id);

        return redirect()->route('control.home');
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(self::MFA_SESSION_KEY);

        if ($id === null) {
            return null;
        }

        $user = User::query()->find($id);

        // Re-verify the type: only super admins authenticate here.
        return $user !== null && $user->type === UserType::SuperAdmin ? $user : null;
    }
}
