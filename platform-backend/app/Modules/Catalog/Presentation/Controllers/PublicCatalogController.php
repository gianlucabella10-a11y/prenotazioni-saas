<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation\Controllers;

use App\Modules\Catalog\Infrastructure\Models\Service;
use App\Modules\Staff\Infrastructure\Models\StaffMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Public, tenant-key-scoped catalog endpoints consumed by the client app
 * before login (docs/25 §3).
 */
final class PublicCatalogController extends Controller
{
    public function services(): JsonResponse
    {
        $services = Service::query()
            ->where('is_active', true)
            ->with(['category', 'variants' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $services->map(static fn (Service $s): array => [
                'uuid' => $s->uuid,
                'name' => $s->name,
                'description' => $s->description,
                'category' => $s->category?->name,
                'variants' => $s->variants->map(static fn ($v): array => [
                    'uuid' => $v->uuid,
                    'name' => $v->name,
                    'duration_minutes' => $v->duration_minutes,
                    'price_cents' => $v->price_cents,
                    'currency' => $v->currency,
                    'is_default' => $v->is_default,
                ])->all(),
            ])->all(),
        ]);
    }

    public function staff(): JsonResponse
    {
        $staff = StaffMember::query()
            ->where('is_bookable', true)
            ->with('services:id,uuid,name')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $staff->map(static fn (StaffMember $m): array => [
                'uuid' => $m->uuid,
                'display_name' => $m->display_name,
                'role_label' => $m->role_label,
                'service_uuids' => $m->services->pluck('uuid')->all(),
            ])->all(),
        ]);
    }
}
