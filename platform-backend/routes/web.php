<?php

declare(strict_types=1);

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
