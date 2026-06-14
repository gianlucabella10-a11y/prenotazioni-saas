<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Presentation\Controllers;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\TenantManagement\Application\ChangeTenantStatus;
use App\Modules\TenantManagement\Application\ProvisionTenant;
use App\Modules\TenantManagement\Domain\TenantStatus;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Super-admin tenant lifecycle (docs/25 §5): provisioning, listing,
 * suspension/reactivation per Flusso 9. La transizione di stato è delegata
 * a ChangeTenantStatus, condivisa con la Control Room (unico punto di
 * verità: stessa validazione, stessa invalidazione cache, stesso audit).
 */
final class AdminTenantController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'string']]);

        $tenants = $currentTenant->bypass(function () use ($data) {
            return Tenant::query()
                ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->orderByDesc('id')
                ->paginate(50);
        });

        return response()->json([
            'data' => collect($tenants->items())->map($this->serialize(...))->all(),
            'next_page' => $tenants->nextPageUrl(),
        ]);
    }

    public function store(Request $request, ProvisionTenant $provision): JsonResponse
    {
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'sector' => ['required', 'in:barber,hair,beauty,dental,medical,physio,consultant,other'],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', 'in:it,en'],
            'plan_code' => ['required', 'string', 'max:32'],
            'app_name' => ['required', 'string', 'min:2', 'max:30'],
            'admin_email' => ['required', 'email', 'max:255'],
            'health_data' => ['sometimes', 'boolean'],
        ]);

        $result = $provision->execute($data, $request->user()->id);

        return response()->json([
            'data' => $this->serialize($result['tenant']),
            'admin' => ['email' => $result['admin']->email, 'uuid' => $result['admin']->uuid],
            // Delivered out-of-band (invitation email) in production; returned
            // here for the operations console flow.
            'invite_token' => $result['invite_token'],
            'tenant_api_key' => $result['tenant']->api_key,
        ], 201);
    }

    public function suspend(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($request, $uuid, TenantStatus::Suspended, 'tenant.suspended');
    }

    public function reactivate(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($request, $uuid, TenantStatus::Active, 'tenant.reactivated');
    }

    public function activate(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($request, $uuid, TenantStatus::Active, 'tenant.activated');
    }

    private function transition(Request $request, string $uuid, TenantStatus $target, string $auditAction): JsonResponse
    {
        $tenant = app(ChangeTenantStatus::class)->execute($uuid, $target, $auditAction, $request->user()->id);

        return response()->json(['data' => $this->serialize($tenant)]);
    }

    /** @return array<string, mixed> */
    private function serialize(Tenant $tenant): array
    {
        return [
            'uuid' => $tenant->uuid,
            'legal_name' => $tenant->legal_name,
            'display_name' => $tenant->display_name,
            'sector' => $tenant->sector,
            'status' => $tenant->status->value,
            'onboarding_state' => $tenant->onboarding_state,
            'created_at' => $tenant->created_at?->toIso8601ZuluString(),
        ];
    }
}
