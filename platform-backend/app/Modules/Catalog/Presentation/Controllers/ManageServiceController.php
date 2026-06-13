<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation\Controllers;

use App\Modules\Catalog\Infrastructure\Models\Service;
use App\Modules\Catalog\Infrastructure\Models\ServiceCategory;
use App\Modules\Catalog\Infrastructure\Models\ServiceVariant;
use App\Modules\TenantManagement\Application\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Tenant-admin service catalog management (docs/25 §4). Soft deletes keep
 * appointment history intact; variants carry duration/price.
 */
final class ManageServiceController extends Controller
{
    public function index(): JsonResponse
    {
        $services = Service::query()
            ->with(['category', 'variants'])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $services->map($this->serialize(...))->all()]);
    }

    public function store(Request $request, QuotaService $quota): JsonResponse
    {
        $data = $this->validatePayload($request);

        $quota->assertWithinQuota('max_services', Service::query()->count());

        $service = Service::query()->create([
            'category_id' => $this->resolveCategoryId($data['category'] ?? null),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        foreach ($data['variants'] as $index => $variant) {
            $service->variants()->create([
                'tenant_id' => $service->tenant_id,
                'name' => $variant['name'],
                'duration_minutes' => $variant['duration_minutes'],
                'buffer_after_minutes' => $variant['buffer_after_minutes'] ?? 0,
                'price_cents' => $variant['price_cents'],
                'currency' => $variant['currency'] ?? 'EUR',
                'is_default' => $index === 0,
            ]);
        }

        return response()->json(['data' => $this->serialize($service->load(['category', 'variants']))], 201);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $service = Service::query()->where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ]);

        if (array_key_exists('category', $data)) {
            $data['category_id'] = $this->resolveCategoryId($data['category']);
            unset($data['category']);
        }

        $service->update($data);

        return response()->json(['data' => $this->serialize($service->load(['category', 'variants']))]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $service = Service::query()->where('uuid', $uuid)->firstOrFail();

        $service->variants()->delete(); // soft
        $service->delete();             // soft

        return response()->json(['status' => 'ok']);
    }

    public function storeVariant(Request $request, string $serviceUuid): JsonResponse
    {
        $service = Service::query()->where('uuid', $serviceUuid)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'buffer_after_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'price_cents' => ['required', 'integer', 'min:0', 'max:10000000'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $variant = $service->variants()->create([
            'tenant_id' => $service->tenant_id,
            'name' => $data['name'],
            'duration_minutes' => $data['duration_minutes'],
            'buffer_after_minutes' => $data['buffer_after_minutes'] ?? 0,
            'price_cents' => $data['price_cents'],
            'currency' => $data['currency'] ?? 'EUR',
            'is_default' => ! $service->variants()->where('is_default', true)->exists(),
        ]);

        return response()->json(['data' => $this->serializeVariant($variant)], 201);
    }

    public function destroyVariant(string $serviceUuid, string $variantUuid): JsonResponse
    {
        $service = Service::query()->where('uuid', $serviceUuid)->firstOrFail();

        $variant = $service->variants()->where('uuid', $variantUuid)->firstOrFail();
        $variant->delete(); // soft: appointment snapshots stay valid

        return response()->json(['status' => 'ok']);
    }

    /** @return array<string, mixed> */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.name' => ['required', 'string', 'max:255'],
            'variants.*.duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'variants.*.buffer_after_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'variants.*.price_cents' => ['required', 'integer', 'min:0', 'max:10000000'],
            'variants.*.currency' => ['nullable', 'string', 'size:3'],
        ]);
    }

    private function resolveCategoryId(?string $categoryName): ?int
    {
        if ($categoryName === null || trim($categoryName) === '') {
            return null;
        }

        return ServiceCategory::query()
            ->firstOrCreate(['name' => trim($categoryName)])
            ->id;
    }

    /** @return array<string, mixed> */
    private function serialize(Service $service): array
    {
        return [
            'uuid' => $service->uuid,
            'name' => $service->name,
            'description' => $service->description,
            'category' => $service->category?->name,
            'is_active' => $service->is_active,
            'sort_order' => $service->sort_order,
            'variants' => $service->variants->map($this->serializeVariant(...))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeVariant(ServiceVariant $variant): array
    {
        return [
            'uuid' => $variant->uuid,
            'name' => $variant->name,
            'duration_minutes' => $variant->duration_minutes,
            'buffer_after_minutes' => $variant->buffer_after_minutes,
            'price_cents' => $variant->price_cents,
            'currency' => $variant->currency,
            'is_default' => $variant->is_default,
            'is_active' => $variant->is_active,
        ];
    }
}
