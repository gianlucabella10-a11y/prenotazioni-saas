<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Presentation\Controllers;

use App\Modules\Scheduling\Application\GetAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** GET /availability — the hottest read path (docs/30 §3, RNF-01). */
final class AvailabilityController extends Controller
{
    public function index(Request $request, GetAvailability $getAvailability): JsonResponse
    {
        $data = $request->validate([
            'location_uuid' => ['required', 'uuid'],
            'variant_uuids' => ['required', 'array', 'min:1', 'max:5'],
            'variant_uuids.*' => ['uuid'],
            'staff_uuid' => ['nullable', 'uuid'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $result = $getAvailability->execute(
            locationUuid: $data['location_uuid'],
            variantUuids: $data['variant_uuids'],
            staffUuid: $data['staff_uuid'] ?? null,
            fromDate: $data['from'],
            toDate: $data['to'],
        );

        return response()->json($result);
    }
}
