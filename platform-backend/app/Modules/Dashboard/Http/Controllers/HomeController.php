<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Foundation\Enums\UserType;
use App\Foundation\Tenancy\CurrentTenant;
use App\Models\User;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Infrastructure\Models\Appointment;
use App\Modules\Staff\Infrastructure\Models\StaffMember;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Home operativa (Fase 2): il colpo d'occhio della giornata, senza
 * grafici — chiarezza operativa (PLAN §4.2). Lo STAFF vede solo la
 * propria agenda.
 */
final class HomeController extends Controller
{
    public function __invoke(Request $request, CurrentTenant $tenant): View
    {
        /** @var User $user */
        $user = $request->user();

        $timezone = $tenant->get()->timezoneObject();
        [$dayStart, $dayEnd] = $this->localDayWindow('today', $timezone);

        $ownStaffId = $this->ownStaffId($user);

        $todayQuery = Appointment::query()
            ->whereIn('status', [AppointmentStatus::Confirmed->value, AppointmentStatus::Requested->value])
            ->where('starts_at', '>=', $dayStart)
            ->where('starts_at', '<', $dayEnd)
            ->with(['customer', 'items.staffMember'])
            ->orderBy('starts_at');

        if ($ownStaffId !== null) {
            $todayQuery->whereHas('items', fn ($q) => $q->where('staff_member_id', $ownStaffId));
        }

        $pendingCount = Appointment::query()
            ->where('status', AppointmentStatus::Requested->value)
            ->when($ownStaffId, fn ($q, $id) => $q->whereHas('items', fn ($iq) => $iq->where('staff_member_id', $id)))
            ->count();

        $upcomingWeek = Appointment::query()
            ->where('status', AppointmentStatus::Confirmed->value)
            ->where('starts_at', '>=', $dayEnd)
            ->where('starts_at', '<', now()->addDays(7))
            ->when($ownStaffId, fn ($q, $id) => $q->whereHas('items', fn ($iq) => $iq->where('staff_member_id', $id)))
            ->count();

        // Top servizi ultimi 30 giorni (solo OWNER: è un dato di gestione).
        $topServices = $ownStaffId !== null ? collect() : DB::table('appointment_items')
            ->join('appointments', 'appointments.id', '=', 'appointment_items.appointment_id')
            ->where('appointment_items.tenant_id', $tenant->id())
            ->whereIn('appointments.status', ['confirmed', 'completed'])
            ->where('appointment_items.starts_at', '>=', now()->subDays(30))
            ->select('appointment_items.service_name_snapshot', DB::raw('count(*) as total'))
            ->groupBy('appointment_items.service_name_snapshot')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return view('dashboard.home', [
            'today' => $todayQuery->get(),
            'pendingCount' => $pendingCount,
            'upcomingWeek' => $upcomingWeek,
            'topServices' => $topServices,
            'activeStaffCount' => StaffMember::query()->where('is_bookable', true)->count(),
            'isOwner' => $user->type === UserType::TenantAdmin,
            'timezone' => $timezone,
        ]);
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} UTC window of a tenant-local day */
    public static function localDayWindow(string $day, DateTimeZone $timezone): array
    {
        $start = new DateTimeImmutable("{$day} 00:00", $timezone);

        return [
            $start->setTimezone(new DateTimeZone('UTC')),
            $start->modify('+1 day')->setTimezone(new DateTimeZone('UTC')),
        ];
    }

    public static function ownStaffId(User $user): ?int
    {
        if ($user->type === UserType::TenantAdmin) {
            return null; // OWNER: nessun filtro
        }

        $id = StaffMember::query()->where('user_id', $user->id)->value('id');

        if ($id === null) {
            // Uno STAFF senza profilo agenda NON deve degradare a "vede
            // tutto": fail-closed (docs/28).
            abort(403, 'Questo account non è collegato a un operatore.');
        }

        return (int) $id;
    }
}
