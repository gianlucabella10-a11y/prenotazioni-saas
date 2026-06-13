<?php

declare(strict_types=1);

namespace App\Modules\Staff\Presentation\Controllers;

use App\Modules\Catalog\Infrastructure\Models\Location;
use App\Modules\Catalog\Infrastructure\Models\Service;
use App\Modules\Scheduling\Application\AvailabilityCacheVersion;
use App\Modules\Staff\Infrastructure\Models\StaffMember;
use App\Modules\TenantManagement\Application\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Staff management (docs/25 §4): profiles, performable services, weekly
 * schedules. Schedule writes invalidate the availability cache for the
 * affected staff member.
 */
final class ManageStaffController extends Controller
{
    public function index(): JsonResponse
    {
        $staff = StaffMember::query()
            ->with(['services:id,uuid', 'schedules'])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $staff->map($this->serialize(...))->all()]);
    }

    public function store(Request $request, QuotaService $quota): JsonResponse
    {
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'role_label' => ['nullable', 'string', 'max:255'],
            'is_bookable' => ['sometimes', 'boolean'],
            'service_uuids' => ['sometimes', 'array'],
            'service_uuids.*' => ['uuid'],
        ]);

        $quota->assertWithinQuota('max_staff', StaffMember::query()->count());

        $staff = StaffMember::query()->create([
            'display_name' => $data['display_name'],
            'role_label' => $data['role_label'] ?? null,
            'is_bookable' => $data['is_bookable'] ?? true,
        ]);

        $this->syncServices($staff, $data['service_uuids'] ?? []);

        return response()->json(['data' => $this->serialize($staff->load(['services:id,uuid', 'schedules']))], 201);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $staff = StaffMember::query()->where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:255'],
            'role_label' => ['nullable', 'string', 'max:255'],
            'is_bookable' => ['sometimes', 'boolean'],
            'service_uuids' => ['sometimes', 'array'],
            'service_uuids.*' => ['uuid'],
        ]);

        $staff->update(collect($data)->except('service_uuids')->all());

        if (array_key_exists('service_uuids', $data)) {
            $this->syncServices($staff, $data['service_uuids']);
        }

        return response()->json(['data' => $this->serialize($staff->load(['services:id,uuid', 'schedules']))]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $staff = StaffMember::query()->where('uuid', $uuid)->firstOrFail();
        $staff->delete(); // soft: appointment history keeps the reference

        return response()->json(['status' => 'ok']);
    }

    /**
     * Replace the weekly schedule atomically: the dashboard always submits
     * the full week, avoiding partial-state surprises.
     */
    public function setSchedules(
        Request $request,
        AvailabilityCacheVersion $cacheVersion,
        string $uuid,
    ): JsonResponse {
        $staff = StaffMember::query()->where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'location_uuid' => ['required', 'uuid'],
            'rules' => ['present', 'array', 'max:21'],
            'rules.*.weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'rules.*.start' => ['required', 'date_format:H:i'],
            'rules.*.end' => ['required', 'date_format:H:i', 'after:rules.*.start'],
        ]);

        $location = Location::query()->where('uuid', $data['location_uuid'])->firstOrFail();

        DB::transaction(function () use ($staff, $location, $data): void {
            $staff->schedules()->where('location_id', $location->id)->delete();

            foreach ($data['rules'] as $rule) {
                $staff->schedules()->create([
                    'tenant_id' => $staff->tenant_id,
                    'location_id' => $location->id,
                    'weekday' => $rule['weekday'],
                    'start_time' => $rule['start'],
                    'end_time' => $rule['end'],
                ]);
            }
        });

        // Schedule changes affect every future day: bump the near horizon.
        foreach (range(0, $location->booking_window_days) as $offset) {
            $cacheVersion->bump($staff->tenant_id, $staff->id, now()->addDays($offset)->format('Y-m-d'));
        }

        return response()->json(['data' => $this->serialize($staff->load(['services:id,uuid', 'schedules']))]);
    }

    /** @param list<string> $serviceUuids */
    private function syncServices(StaffMember $staff, array $serviceUuids): void
    {
        $ids = Service::query()->whereIn('uuid', $serviceUuids)->pluck('id');

        $staff->services()->sync(
            $ids->mapWithKeys(fn (int $id): array => [$id => ['tenant_id' => $staff->tenant_id]])->all()
        );
    }

    /** @return array<string, mixed> */
    private function serialize(StaffMember $staff): array
    {
        return [
            'uuid' => $staff->uuid,
            'display_name' => $staff->display_name,
            'role_label' => $staff->role_label,
            'is_bookable' => $staff->is_bookable,
            'service_uuids' => $staff->services->pluck('uuid')->all(),
            'schedules' => $staff->schedules->map(static fn ($s): array => [
                'weekday' => $s->weekday,
                'start' => substr((string) $s->start_time, 0, 5),
                'end' => substr((string) $s->end_time, 0, 5),
            ])->all(),
        ];
    }
}
