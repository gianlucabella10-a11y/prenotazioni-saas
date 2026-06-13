<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Presentation\Controllers;

use App\Foundation\Http\ApiException;
use App\Models\User;
use App\Modules\Customers\Infrastructure\Models\Customer;
use App\Modules\Scheduling\Application\BookAppointment;
use App\Modules\Scheduling\Application\CancelAppointment;
use App\Modules\Scheduling\Infrastructure\Models\Appointment;
use App\Modules\Scheduling\Presentation\Resources\AppointmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * Customer-facing appointment endpoints (docs/25 §3). Every query is
 * constrained to the authenticated customer's own records: ownership is the
 * authorization rule here, on top of tenant scoping.
 */
final class AppointmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['scope' => ['nullable', 'in:upcoming,past']]);

        $query = Appointment::query()
            ->where('customer_id', $this->currentCustomer($request)->id)
            ->with(['items.staffMember', 'location']);

        match ($data['scope'] ?? 'upcoming') {
            'past' => $query->where('starts_at', '<', now())->orderByDesc('starts_at'),
            default => $query->where('starts_at', '>=', now())->orderBy('starts_at'),
        };

        return AppointmentResource::collection($query->cursorPaginate(20));
    }

    public function show(Request $request, string $uuid): AppointmentResource
    {
        return new AppointmentResource($this->ownAppointment($request, $uuid));
    }

    public function store(Request $request, BookAppointment $bookAppointment): JsonResponse
    {
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 64) {
            throw ApiException::unprocessable('missing_idempotency_key', 'Provide an Idempotency-Key header (max 64 chars).');
        }

        $data = $request->validate([
            'location_uuid' => ['required', 'uuid'],
            'variant_uuids' => ['required', 'array', 'min:1', 'max:5'],
            'variant_uuids.*' => ['uuid'],
            'staff_uuid' => ['required', 'uuid'],
            'starts_at' => ['required', 'date'],
        ]);

        $appointment = $bookAppointment->execute(
            customer: $this->currentCustomer($request),
            locationUuid: $data['location_uuid'],
            variantUuids: $data['variant_uuids'],
            staffUuid: $data['staff_uuid'],
            startsAtIso: $data['starts_at'],
            idempotencyKey: $idempotencyKey,
        );

        $appointment->load(['items.staffMember', 'location']);

        return (new AppointmentResource($appointment))->response()->setStatusCode(201);
    }

    public function cancel(Request $request, CancelAppointment $cancelAppointment, string $uuid): AppointmentResource
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $appointment = $cancelAppointment->byCustomer(
            $this->ownAppointment($request, $uuid),
            $data['reason'] ?? null,
        );

        return new AppointmentResource($appointment->load(['items.staffMember', 'location']));
    }

    private function ownAppointment(Request $request, string $uuid): Appointment
    {
        return Appointment::query()
            ->where('uuid', $uuid)
            ->where('customer_id', $this->currentCustomer($request)->id)
            ->with(['items.staffMember', 'location'])
            ->firstOrFail();
    }

    private function currentCustomer(Request $request): Customer
    {
        /** @var User $user */
        $user = $request->user();

        $customer = Customer::query()->where('user_id', $user->id)->first();

        if ($customer === null) {
            throw ApiException::forbidden('customer_profile_missing', 'No customer profile is associated with this account.');
        }

        return $customer;
    }
}
