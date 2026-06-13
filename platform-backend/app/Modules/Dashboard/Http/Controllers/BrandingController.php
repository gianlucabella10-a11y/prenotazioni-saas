<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Tenancy\CurrentTenant;
use App\Foundation\Tenancy\TenantRegistry;
use App\Modules\Branding\Application\ContrastValidator;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\Catalog\Infrastructure\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * "Personalizzazione App" (Fase 7): brand (nome, tagline, colori con
 * validazione contrasto WCAG) + URL legali + contatti sede. Ogni modifica
 * bumpa config_version e invalida il registry: l'app mobile si aggiorna
 * alla prossima apertura (docs/27 §2) — la prova vivente del white label.
 */
final class BrandingController extends Controller
{
    public function index(): View
    {
        return view('dashboard.branding.index', [
            'brand' => BrandProfile::query()->firstOrFail(),
            'location' => Location::query()->orderBy('id')->firstOrFail(),
        ]);
    }

    public function updateBrand(
        Request $request,
        ContrastValidator $contrast,
        TenantRegistry $registry,
        CurrentTenant $tenant,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'min:2', 'max:30'],
            'tagline' => ['nullable', 'string', 'max:80'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'privacy_policy_url' => ['nullable', 'url:https', 'max:255'],
            'terms_url' => ['nullable', 'url:https', 'max:255'],
            'support_url' => ['nullable', 'url:https', 'max:255'],
        ], [], ['app_name' => 'nome app', 'primary_color' => 'colore primario']);

        $brand = BrandProfile::query()->firstOrFail();

        $theme = $brand->theme;
        $theme['colors']['primary'] = $data['primary_color'];
        $theme['colors']['secondary'] = $data['secondary_color'];

        $minimum = (float) config('branding.minimum_contrast_ratio');

        if (! $contrast->meetsMinimum(
            $theme['colors']['primary'],
            $theme['colors']['on_primary'] ?? '#FFFFFF',
            $minimum,
        )) {
            return back()->withErrors([
                'primary_color' => "Il colore primario non garantisce un contrasto leggibile ({$minimum}:1): scegline uno più scuro o più chiaro.",
            ])->withInput();
        }

        $brand->update([
            'app_name' => $data['app_name'],
            'tagline' => $data['tagline'] ?? null,
            'primary_color' => $data['primary_color'],
            'secondary_color' => $data['secondary_color'],
            'theme' => $theme,
            'privacy_policy_url' => $data['privacy_policy_url'] ?? null,
            'terms_url' => $data['terms_url'] ?? null,
            'support_url' => $data['support_url'] ?? null,
            'contrast_validated' => true,
        ]);
        $brand->bumpConfigVersion();

        $registry->forget($tenant->id());
        $audit->log('brand.updated', $request->user()->id, ['via' => 'dashboard']);

        return back()->with('status', 'Personalizzazione salvata: l\'app si aggiorna alla prossima apertura.');
    }

    /** Contatti e anagrafica sede mostrati nell'app cliente. */
    public function updateContacts(
        Request $request,
        TenantRegistry $registry,
        CurrentTenant $tenant,
    ): RedirectResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ], [], ['name' => 'nome sede']);

        Location::query()->orderBy('id')->firstOrFail()->update($data);

        // Le sedi viaggiano nel config: bump per invalidare l'ETag client.
        $brand = BrandProfile::query()->firstOrFail();
        $brand->bumpConfigVersion();
        $registry->forget($tenant->id());

        return back()->with('status', 'Contatti aggiornati.');
    }
}
