<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Foundation\Enums\UserType;
use App\Foundation\Tenancy\CurrentTenant;
use App\Models\User;
use App\Modules\Scheduling\Application\CancelAppointment;
use App\Modules\Scheduling\Application\TransitionAppointment;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Infrastructure\Models\Appointment;
use App\Modules\Staff\Infrastructure\Models\StaffMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Calendario gestionale (Fase 6). Le azioni di stato riusano i servizi
 * applicativi esistenti (TransitionAppointment / CancelAppointment): le
 * notifiche al cliente e l'invalidazione della disponibilità sono le
 * stesse identiche del flusso API — zero duplicazione (PLAN §3).
 *
 * RBAC: lo STAFF vede e agisce SOLO sui propri appuntamenti (docs/26 §5).
 */
final class BookingsController extends Controller
{
    public function index(Request $request, CurrentTenant $tenant): View
    {
        /** @var User $user */
        $user = $request->user();

        $day = (string) $request->query('date', now()->format('Y-m-d'));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $day = now()->format('Y-m-d');
        }

        [$dayStart, $dayEnd] = HomeController::localDayWindow($day, $tenant->get()->timezoneObject());

        $ownStaffId = HomeController::ownStaffId($user);

        $staffFilterUuid = (string) $request->query('staff', '');

        $query = Appointment::query()
            ->where('starts_at', '>=', $dayStart)
            ->where('starts_at', '<', $dayEnd)
            ->with(['customer', 'items.staffMember'])
            ->orderBy('starts_at');

        if ($ownStaffId !== null) {
            $query->whereHas('items', fn ($q) => $q->where('staff_member_id', $ownStaffId));
        } elseif ($staffFilterUuid !== '') {
            $query->whereHas('items.staffMember', fn ($q) => $q->where('uuid', $staffFilterUuid));
        }

        $pending = Appointment::query()
            ->where('status', AppointmentStatus::Requested->value)
            ->when($ownStaffId, fn ($q, $id) => $q->whereHas('items', fn ($iq) => $iq->where('staff_member_id', $id)))
            ->with(['customer', 'items.staffMember'])
            ->orderBy('starts_at')
            ->get();

        return view('dashboard.bookings.index', [
            'day' => $day,
            'appointments' => $query->get(),
            'pending' => $pending,
            'staffList' => $ownStaffId === null
                ? StaffMember::query()->orderBy('display_name')->get(['uuid', 'display_name'])
                : collect(),
            'staffFilter' => $staffFilterUuid,
            'timezone' => $tenant->get()->timezoneObject(),
            'isOwner' => $user->type === UserType::TenantAdmin,
        ]);
    }

    public function confirm(Request $request, TransitionAppointment $transition, string $uuid): RedirectResponse
    {
        $transition->confirm($this->actionable($request, $uuid), $request->user());

        return back()->with('status', 'Prenotazione confermata.');
    }

    public function complete(Request $request, TransitionAppointment $transition, string $uuid): RedirectResponse
    {
        $transition->complete($this->actionable($request, $uuid), $request->user());

        return back()->with('status', 'Appuntamento completato.');
    }

    public function noShow(Request $request, TransitionAppointment $transition, string $uuid): RedirectResponse
    {
        $transition->markNoShow($this->actionable($request, $uuid), $request->user());

        return back()->with('status', 'No-show registrato.');
    }

    public function cancel(Request $request, CancelAppointment $cancel, string $uuid): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $cancel->byTenant(
            $this->actionable($request, $uuid),
            $request->user()->id,
            $data['reason'] ?? null,
        );

        return back()->with('status', 'Prenotazione annullata: il cliente è stato avvisato.');
    }

    /**
     * Lo STAFF può agire solo su appuntamenti che lo includono: l'uuid
     * fuori dal proprio perimetro risponde 404 (docs/28 §2).
     */
    private function actionable(Request $request, string $uuid): Appointment
    {
        $query = Appointment::query()->where('uuid', $uuid);

        $ownStaffId = HomeController::ownStaffId($request->user());

        if ($ownStaffId !== null) {
            $query->whereHas('items', fn ($q) => $q->where('staff_member_id', $ownStaffId));
        }

        return $query->firstOrFail();
    }
}
