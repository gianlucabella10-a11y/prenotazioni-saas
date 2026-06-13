<?php

declare(strict_types=1);

use App\Foundation\Auth\Controllers\AuthController;
use App\Modules\Branding\Presentation\Controllers\AppConfigController;
use App\Modules\Customers\Presentation\Controllers\MeController;
use App\Modules\Notifications\Presentation\Controllers\DeviceController;
use App\Modules\Branding\Presentation\Controllers\ManageBrandController;
use App\Modules\Catalog\Presentation\Controllers\ManageServiceController;
use App\Modules\Catalog\Presentation\Controllers\PublicCatalogController;
use App\Modules\Scheduling\Presentation\Controllers\AppointmentController;
use App\Modules\Scheduling\Presentation\Controllers\AvailabilityController;
use App\Modules\Scheduling\Presentation\Controllers\ManageAgendaController;
use App\Modules\Staff\Presentation\Controllers\ManageStaffController;
use App\Modules\TenantManagement\Presentation\Controllers\AdminTenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 (docs/25) — mounted under /api/v1 (bootstrap/app.php)
|--------------------------------------------------------------------------
*/

/*
| Public surface of the white label client app: tenant resolved from the
| X-Tenant-Key compiled into the build. The config endpoint serves every
| tenant status (courtesy screens); everything else requires an operating
| tenant.
*/
Route::middleware(['tenant.key', 'throttle:api'])->group(function (): void {
    Route::get('/app/config', [AppConfigController::class, 'show']);
});

Route::middleware(['tenant.key', 'tenant.operating'])->group(function (): void {
    Route::middleware('throttle:api')->group(function (): void {
        Route::get('/catalog/services', [PublicCatalogController::class, 'services']);
        Route::get('/staff', [PublicCatalogController::class, 'staff']);
    });

    Route::get('/availability', [AvailabilityController::class, 'index'])
        ->middleware('throttle:availability');

    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
    });
});

/*
| Token lifecycle: the refresh token itself is the credential, no tenant
| key needed (docs/26 §3).
*/
Route::middleware('throttle:auth')->group(function (): void {
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

/*
| MFA challenge endpoints: accept ONLY scope-mfa tokens (docs/26 §4).
*/
Route::middleware(['auth:api', 'throttle:auth'])->group(function (): void {
    Route::post('/auth/mfa/verify', [AuthController::class, 'mfaVerify']);
    Route::post('/auth/mfa/setup', [AuthController::class, 'mfaSetup']);
    Route::post('/auth/mfa/confirm', [AuthController::class, 'mfaConfirm']);
});

/*
| Customer surface: authenticated end customers.
|
| Account-level endpoints (verification, profile read, deletion) work even
| BEFORE email verification — Apple requires account deletion to always be
| reachable. Identity-bound features (booking, history, devices, profile
| edits) are gated by `verified` (Fase 1, S1 fix).
*/
Route::middleware(['auth:api', 'auth.full', 'user.type:customer', 'throttle:api'])
    ->group(function (): void {
        Route::post('/auth/email/verify', [AuthController::class, 'verifyEmail'])
            ->middleware('throttle:auth');
        Route::post('/auth/email/resend', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:auth');

        Route::get('/me', [MeController::class, 'show']);
        Route::delete('/me', [MeController::class, 'destroy']);

        Route::middleware(['verified', 'tenant.operating'])->group(function (): void {
            Route::patch('/me', [MeController::class, 'update']);
            Route::put('/me/consents', [MeController::class, 'updateConsents']);
            Route::put('/me/devices', [DeviceController::class, 'store']);
            Route::delete('/me/devices', [DeviceController::class, 'destroy']);

            Route::get('/appointments', [AppointmentController::class, 'index']);
            Route::post('/appointments', [AppointmentController::class, 'store']);
            Route::get('/appointments/{uuid}', [AppointmentController::class, 'show']);
            Route::post('/appointments/{uuid}/cancel', [AppointmentController::class, 'cancel']);
        });
    });

/*
| Management surface: tenant admin + staff (docs/25 §4). Configuration
| writes are tenant-admin only; the agenda is shared with RBAC inside the
| controller (staff see only themselves).
*/
Route::prefix('manage')
    ->middleware(['auth:api', 'auth.full', 'user.type:tenant_admin,staff', 'throttle:api'])
    ->group(function (): void {
        Route::get('/agenda', [ManageAgendaController::class, 'index']);
        Route::post('/appointments/{uuid}/confirm', [ManageAgendaController::class, 'confirm']);
        Route::post('/appointments/{uuid}/complete', [ManageAgendaController::class, 'complete']);
        Route::post('/appointments/{uuid}/no-show', [ManageAgendaController::class, 'noShow']);
        Route::post('/appointments/{uuid}/cancel', [ManageAgendaController::class, 'cancel']);

        Route::middleware('user.type:tenant_admin')->group(function (): void {
            Route::get('/services', [ManageServiceController::class, 'index']);
            Route::post('/services', [ManageServiceController::class, 'store']);
            Route::patch('/services/{uuid}', [ManageServiceController::class, 'update']);
            Route::delete('/services/{uuid}', [ManageServiceController::class, 'destroy']);
            Route::post('/services/{uuid}/variants', [ManageServiceController::class, 'storeVariant']);
            Route::delete('/services/{serviceUuid}/variants/{variantUuid}', [ManageServiceController::class, 'destroyVariant']);

            Route::get('/staff', [ManageStaffController::class, 'index']);
            Route::post('/staff', [ManageStaffController::class, 'store']);
            Route::patch('/staff/{uuid}', [ManageStaffController::class, 'update']);
            Route::delete('/staff/{uuid}', [ManageStaffController::class, 'destroy']);
            Route::put('/staff/{uuid}/schedules', [ManageStaffController::class, 'setSchedules']);

            Route::get('/brand', [ManageBrandController::class, 'show']);
            Route::put('/brand', [ManageBrandController::class, 'update']);
        });
    });

/*
| Platform surface: super admin only (docs/25 §5).
*/
Route::prefix('admin')
    ->middleware(['auth:api', 'auth.full', 'user.type:super_admin', 'throttle:api'])
    ->group(function (): void {
        Route::get('/tenants', [AdminTenantController::class, 'index']);
        Route::post('/tenants', [AdminTenantController::class, 'store']);
        Route::post('/tenants/{uuid}/suspend', [AdminTenantController::class, 'suspend']);
        Route::post('/tenants/{uuid}/reactivate', [AdminTenantController::class, 'reactivate']);
        Route::post('/tenants/{uuid}/activate', [AdminTenantController::class, 'activate']);
    });
