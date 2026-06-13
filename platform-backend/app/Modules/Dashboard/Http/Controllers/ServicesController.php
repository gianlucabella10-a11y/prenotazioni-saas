<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Modules\Catalog\Infrastructure\Models\Service;
use App\Modules\Catalog\Infrastructure\Models\ServiceCategory;
use App\Modules\TenantManagement\Application\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Gestione servizi (Fase 3): CRUD con la variante default come unità di
 * prezzo/durata (docs/24 §4 — ogni servizio ha almeno una variante).
 * Eliminazione = soft delete: lo storico appuntamenti resta integro.
 */
final class ServicesController extends Controller
{
    public function index(): View
    {
        return view('dashboard.services.index', [
            'services' => Service::query()
                ->with(['category', 'variants'])
                ->orderBy('sort_order')->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.services.form', ['service' => null]);
    }

    public function store(Request $request, QuotaService $quota): RedirectResponse
    {
        $data = $this->validated($request);

        $quota->assertWithinQuota('max_services', Service::query()->count());

        $service = Service::query()->create([
            'category_id' => $this->categoryId($data['category'] ?? null),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $service->variants()->create([
            'tenant_id' => $service->tenant_id,
            'name' => 'Standard',
            'duration_minutes' => $data['duration_minutes'],
            'buffer_after_minutes' => $data['buffer_after_minutes'] ?? 0,
            'price_cents' => (int) round($data['price'] * 100),
            'currency' => 'EUR',
            'is_default' => true,
        ]);

        return redirect()->route('dashboard.services.index')
            ->with('status', 'Servizio creato.');
    }

    public function edit(string $uuid): View
    {
        return view('dashboard.services.form', [
            'service' => Service::query()->where('uuid', $uuid)->with('variants')->firstOrFail(),
        ]);
    }

    public function update(Request $request, string $uuid): RedirectResponse
    {
        $service = Service::query()->where('uuid', $uuid)->with('variants')->firstOrFail();
        $data = $this->validated($request);

        $service->update([
            'category_id' => $this->categoryId($data['category'] ?? null),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $service->variants()->where('is_default', true)->first()?->update([
            'duration_minutes' => $data['duration_minutes'],
            'buffer_after_minutes' => $data['buffer_after_minutes'] ?? 0,
            'price_cents' => (int) round($data['price'] * 100),
        ]);

        return redirect()->route('dashboard.services.index')
            ->with('status', 'Servizio aggiornato.');
    }

    /** Soft delete: gli snapshot negli appuntamenti passati non si toccano. */
    public function destroy(string $uuid): RedirectResponse
    {
        $service = Service::query()->where('uuid', $uuid)->firstOrFail();

        $service->variants()->delete();
        $service->delete();

        return redirect()->route('dashboard.services.index')
            ->with('status', 'Servizio eliminato (lo storico resta disponibile).');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'buffer_after_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
        ], [], [
            'name' => 'nome',
            'price' => 'prezzo',
            'duration_minutes' => 'durata',
        ]);
    }

    private function categoryId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return ServiceCategory::query()->firstOrCreate(['name' => trim($name)])->id;
    }
}
