<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Presentation\Controllers;

use App\Foundation\Enums\UserType;
use App\Foundation\Http\ApiException;
use App\Models\User;
use App\Modules\Scheduling\Application\CancelAppointment;
use App\Modules\Scheduling\Application\TransitionAppointment;
use App\Modules\Scheduling\Infrastructure\Models\Appointment;
use App\Modules\Scheduling\Presentation\Resources\AppointmentResource;
use App\Modules\Staff\Infrastructure\Models\StaffMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Staff-side agenda (docs/25 §4, docs/31 §5). RBAC: a plain staff member
 * reads only their own agenda and acts only on their own appointments; the
 * tenant admin sees and manages everything (docs/26 §5).
 */
final class ManageAgendaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'staff_uuid' => ['nullable', 'uuid'],
        ]);

        // The agenda day is the TENANT-LOCAL day, not the UTC one: convert
        // the local midnight bounds to a UTC range (docs/30 §2).
        $timezone = new \DateTimeZone(app(\App\Foundation\Tenancy\CurrentTenant::class)->get()->timezone);
        $dayStart = (new \DateTimeImmutable("{$data['date']} 00:00", $timezone))->setTimezone(new \DateTimeZone('UTC'));
        $dayEnd = (new \DateTimeImmutable("{$data['date']} 00:00", $timezone))->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'));

        $query = Appointment::query()
            ->where('starts_at', '>=', $dayStart)
            ->where('starts_at', '<', $dayEnd)
            ->with(['items.staffMember', 'location', 'customer'])
            ->orderBy('starts_at');

        $staffFilter = $this->resolveStaffScope($request->user(), $data['staff_uuid'] ?? null);

        if ($staffFilter !== null) {
            $query->whereHas('items', fn ($q) => $q->where('staff_member_id', $staffFilter->id));
        }

        return response()->json([
            'data' => $query->get()->map(static function (Appointment $a): array {
                $resource = (new AppointmentResource($a))->resolve();
                $resource['customer'] = [
                    'uuid' => $a->customer->uuid,
                    'name' => $a->customer->fullName(),
                    'no_show_count' => $a->customer->no_show_count,
                ];

                return $resource;
            })->all(),
        ]);
    }

    public function confirm(Request $request, TransitionAppointment $transition, string $uuid): AppointmentResource
    {
        $appointment = $this->actionableAppointment($request->user(), $uuid);

        return new AppointmentResource(
            $transition->confirm($appointment, $request->user())->load(['items.staffMember', 'location'])
        );
    }

    public function complete(Request $request, TransitionAppointment $transition, string $uuid): AppointmentResource
    {
        $appointment = $this->actionableAppointment($request->user(), $uuid);

        return new AppointmentResource(
            $transition->complete($appointment, $request->user())->load(['items.staffMember', 'location'])
        );
    }

    public function noShow(Request $request, TransitionAppointment $transition, string $uuid): AppointmentResource
    {
        $appointment = $this->actionableAppointment($request->user(), $uuid);

        return new AppointmentResource(
            $transition->markNoShow($appointment, $request->user())->load(['items.staffMember', 'location'])
        );
    }

    public function cancel(Request $request, CancelAppointment $cancel, string $uuid): AppointmentResource
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $appointment = $this->actionableAppointment($request->user(), $uuid);

        return new AppointmentResource(
            $cancel->byTenant($appointment, $request->user()->id, $data['reason'] ?? null)
                ->load(['items.staffMember', 'location'])
        );
    }

    /**
     * Plain staff act only on appointments that include them; uuid probing
     * outside that scope reads as 404 (docs/28 §2).
     */
    private function actionableAppointment(User $actor, string $uuid): Appointment
    {
        $query = Appointment::query()->where('uuid', $uuid);

        $ownScope = $this->resolveStaffScope($actor, null);

        if ($ownScope !== null) {
            $query->whereHas('items', fn ($q) => $q->where('staff_member_id', $ownScope->id));
        }

        return $query->firstOrFail();
    }

    /**
     * Admin: optional filter by any staff member. Staff: forced to self.
     */
    private function resolveStaffScope(User $actor, ?string $requestedStaffUuid): ?StaffMember
    {
        if ($actor->type === UserType::TenantAdmin) {
            return $requestedStaffUuid === null
                ? null
                : StaffMember::query()->where('uuid', $requestedStaffUuid)->firstOrFail();
        }

        $own = StaffMember::query()->where('user_id', $actor->id)->first();

        if ($own === null) {
            throw ApiException::forbidden('no_staff_profile', 'This account has no staff profile.');
        }

        return $own;
    }
}
