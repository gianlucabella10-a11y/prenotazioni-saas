<?php

declare(strict_types=1);

use App\Modules\AppFactory\Http\Controllers\AppProjectController;
use App\Modules\ControlRoom\Http\Controllers\ControlRoomAuthController;
use App\Modules\ControlRoom\Http\Controllers\TenantBrandController;
use App\Modules\ControlRoom\Http\Controllers\TenantInviteController;
use App\Modules\ControlRoom\Http\Controllers\TenantsController;
use App\Modules\Dashboard\Http\Controllers\AvailabilityController;
use App\Modules\Dashboard\Http\Controllers\BookingsController;
use App\Modules\Dashboard\Http\Controllers\BrandingController;
use App\Modules\Dashboard\Http\Controllers\HomeController;
use App\Modules\Dashboard\Http\Controllers\ServicesController;
use App\Modules\Dashboard\Http\Controllers\StaffController;
use App\Modules\Dashboard\Http\Controllers\WebAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Professional Management Dashboard (PLAN: PROFESSIONAL_DASHBOARD_PLAN.md)
|--------------------------------------------------------------------------
| Superficie web a sessione (docs/26 review #26): CSRF attivo, niente JWT.
| RBAC: OWNER (tenant_admin) gestisce tutto; STAFF vede home e
| prenotazioni proprie. Il tenant deriva SOLO dall'utente di sessione.
*/

Route::redirect('/', '/dashboard/login');

Route::prefix('dashboard')->group(function (): void {
    // --- Area pubblica (guest) ---
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [WebAuthController::class, 'showLogin'])->name('dashboard.login');
        Route::post('/login', [WebAuthController::class, 'login'])
            ->middleware('throttle:auth')->name('dashboard.login.post');

        Route::get('/mfa', [WebAuthController::class, 'showMfaChallenge'])->name('dashboard.mfa.challenge');
        Route::post('/mfa', [WebAuthController::class, 'verifyMfa'])
            ->middleware('throttle:auth')->name('dashboard.mfa.verify');
        Route::get('/mfa/setup', [WebAuthController::class, 'showMfaSetup'])->name('dashboard.mfa.setup');
        Route::post('/mfa/setup', [WebAuthController::class, 'confirmMfaSetup'])
            ->middleware('throttle:auth')->name('dashboard.mfa.confirm');

        Route::get('/invito', [WebAuthController::class, 'showInvite'])->name('dashboard.invite');
        Route::post('/invito', [WebAuthController::class, 'acceptInvite'])
            ->middleware('throttle:auth')->name('dashboard.invite.accept');
    });

    // --- Area autenticata (OWNER + STAFF) ---
    Route::middleware(['auth:web', 'tenant.dashboard'])->group(function (): void {
        Route::post('/logout', [WebAuthController::class, 'logout'])->name('dashboard.logout');

        Route::get('/home', HomeController::class)->name('dashboard.home');

        Route::get('/prenotazioni', [BookingsController::class, 'index'])->name('dashboard.bookings.index');
        Route::post('/prenotazioni/{uuid}/conferma', [BookingsController::class, 'confirm'])->name('dashboard.bookings.confirm');
        Route::post('/prenotazioni/{uuid}/completa', [BookingsController::class, 'complete'])->name('dashboard.bookings.complete');
        Route::post('/prenotazioni/{uuid}/no-show', [BookingsController::class, 'noShow'])->name('dashboard.bookings.noshow');
        Route::post('/prenotazioni/{uuid}/annulla', [BookingsController::class, 'cancel'])->name('dashboard.bookings.cancel');

        // --- Configurazione: solo OWNER ---
        Route::middleware('owner')->group(function (): void {
            Route::get('/servizi', [ServicesController::class, 'index'])->name('dashboard.services.index');
            Route::get('/servizi/nuovo', [ServicesController::class, 'create'])->name('dashboard.services.create');
            Route::post('/servizi', [ServicesController::class, 'store'])->name('dashboard.services.store');
            Route::get('/servizi/{uuid}', [ServicesController::class, 'edit'])->name('dashboard.services.edit');
            Route::put('/servizi/{uuid}', [ServicesController::class, 'update'])->name('dashboard.services.update');
            Route::delete('/servizi/{uuid}', [ServicesController::class, 'destroy'])->name('dashboard.services.destroy');

            Route::get('/operatori', [StaffController::class, 'index'])->name('dashboard.staff.index');
            Route::get('/operatori/nuovo', [StaffController::class, 'create'])->name('dashboard.staff.create');
            Route::post('/operatori', [StaffController::class, 'store'])->name('dashboard.staff.store');
            Route::get('/operatori/{uuid}', [StaffController::class, 'edit'])->name('dashboard.staff.edit');
            Route::put('/operatori/{uuid}', [StaffController::class, 'update'])->name('dashboard.staff.update');
            Route::delete('/operatori/{uuid}', [StaffController::class, 'destroy'])->name('dashboard.staff.destroy');

            Route::get('/disponibilita', [AvailabilityController::class, 'index'])->name('dashboard.availability.index');
            Route::put('/disponibilita/orari-sede', [AvailabilityController::class, 'updateLocationHours'])->name('dashboard.availability.hours');
            Route::post('/disponibilita/chiusure', [AvailabilityController::class, 'storeException'])->name('dashboard.availability.exceptions.store');
            Route::delete('/disponibilita/chiusure/{uuid}', [AvailabilityController::class, 'destroyException'])->name('dashboard.availability.exceptions.destroy');

            Route::get('/personalizzazione', [BrandingController::class, 'index'])->name('dashboard.branding.index');
            Route::put('/personalizzazione/brand', [BrandingController::class, 'updateBrand'])->name('dashboard.branding.brand');
            Route::put('/personalizzazione/contatti', [BrandingController::class, 'updateContacts'])->name('dashboard.branding.contacts');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Control Room — pannello proprietario super-admin (SOLO interno)
|--------------------------------------------------------------------------
| Superficie isolata dal dashboard cliente: guard `admin` dedicata +
| middleware `control.admin`. Nessun tenant_admin/staff/customer entra qui.
*/
Route::prefix('control-room')->group(function (): void {
    Route::middleware('guest:admin')->group(function (): void {
        Route::get('/login', [ControlRoomAuthController::class, 'showLogin'])->name('control.login');
        Route::post('/login', [ControlRoomAuthController::class, 'login'])
            ->middleware('throttle:auth')->name('control.login.post');

        Route::get('/mfa', [ControlRoomAuthController::class, 'showMfaChallenge'])->name('control.mfa.challenge');
        Route::post('/mfa', [ControlRoomAuthController::class, 'verifyMfa'])
            ->middleware('throttle:auth')->name('control.mfa.verify');
        Route::get('/mfa/setup', [ControlRoomAuthController::class, 'showMfaSetup'])->name('control.mfa.setup');
        Route::post('/mfa/setup', [ControlRoomAuthController::class, 'confirmMfaSetup'])
            ->middleware('throttle:auth')->name('control.mfa.confirm');
    });

    Route::middleware(['auth:admin', 'control.admin'])->group(function (): void {
        Route::post('/logout', [ControlRoomAuthController::class, 'logout'])->name('control.logout');

        Route::get('/', [TenantsController::class, 'index'])->name('control.home');
        Route::get('/clienti', [TenantsController::class, 'index'])->name('control.tenants.index');
        Route::get('/clienti/nuovo', [TenantsController::class, 'create'])->name('control.tenants.create');
        Route::post('/clienti', [TenantsController::class, 'store'])->name('control.tenants.store');
        Route::get('/clienti/{uuid}', [TenantsController::class, 'show'])->name('control.tenants.show');
        Route::post('/clienti/{uuid}/sospendi', [TenantsController::class, 'suspend'])->name('control.tenants.suspend');
        Route::post('/clienti/{uuid}/riattiva', [TenantsController::class, 'reactivate'])->name('control.tenants.reactivate');
        Route::post('/clienti/{uuid}/attiva', [TenantsController::class, 'activate'])->name('control.tenants.activate');

        Route::post('/clienti/{uuid}/invito/rigenera', [TenantInviteController::class, 'regenerate'])->name('control.tenants.invite.regenerate');
        Route::post('/clienti/{uuid}/invito/revoca', [TenantInviteController::class, 'revoke'])->name('control.tenants.invite.revoke');

        Route::put('/clienti/{uuid}/brand', [TenantBrandController::class, 'update'])->name('control.tenants.brand');
        Route::post('/clienti/{uuid}/logo', [TenantBrandController::class, 'uploadLogo'])->name('control.tenants.logo');

        // App Factory (FASE 1): App Project + generazione manifest.
        Route::get('/apps', [AppProjectController::class, 'index'])->name('control.apps.index');
        // Osservabilità flotta (FASE 3): prima di /apps/{uuid} per non essere oscurata.
        Route::get('/apps/flotta', [AppProjectController::class, 'fleet'])->name('control.apps.fleet');
        Route::get('/apps/{uuid}', [AppProjectController::class, 'show'])->name('control.apps.show');
        Route::put('/apps/{uuid}/template', [AppProjectController::class, 'updateTemplate'])->name('control.apps.template');
        Route::post('/apps/{uuid}/genera', [AppProjectController::class, 'generate'])->name('control.apps.generate');
        Route::post('/apps/{uuid}/build', [AppProjectController::class, 'dispatchBuild'])->name('control.apps.build');
        Route::post('/apps/{uuid}/rollback', [AppProjectController::class, 'rollbackAssets'])->name('control.apps.rollback');
        Route::get('/apps/{uuid}/download/{build}', [AppProjectController::class, 'download'])->name('control.apps.download');
        Route::get('/apps/{uuid}/package', [AppProjectController::class, 'downloadPackage'])->name('control.apps.package');
    });
});
