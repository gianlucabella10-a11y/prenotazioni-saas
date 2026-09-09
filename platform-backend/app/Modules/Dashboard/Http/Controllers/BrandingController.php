<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Tenancy\CurrentTenant;
use App\Foundation\Tenancy\TenantRegistry;
use App\Modules\Branding\Application\ContrastValidator;
use App\Modules\Branding\Application\DeriveDarkPalette;
use App\Modules\AppFactory\Domain\BuildImpactMatrix;
use App\Modules\Branding\Application\GenerateBrandAssets;
use App\Modules\Branding\Application\StoreBrandLogo;
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
    public function index(BuildImpactMatrix $matrix): View
    {
        return view('dashboard.branding.index', [
            'brand' => BrandProfile::query()->firstOrFail(),
            'location' => Location::query()->orderBy('id')->firstOrFail(),
            'buildMatrix' => $matrix->all(),
        ]);
    }

    public function updateBrand(
        Request $request,
        ContrastValidator $contrast,
        DeriveDarkPalette $darkPalette,
        TenantRegistry $registry,
        CurrentTenant $tenant,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'min:2', 'max:30'],
            'tagline' => ['nullable', 'string', 'max:80'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            // Tema premium (Fase 1): modalità, accent, sfondo, semantici, stile.
            'mode' => ['sometimes', 'in:light,dark,system'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'success_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'warning_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            // Stile forma+ombra in un unico controllo intuitivo (Rounded/Flat).
            'style' => ['sometimes', 'in:rounded,flat'],
            'shadow_level' => ['sometimes', 'numeric', 'min:0', 'max:4'],
            // App Identity (Fase 2): densità visiva dell'intera app.
            'density' => ['sometimes', 'in:comfortable,standard,compact'],
            // Customer Experience (Fase 5): copy/immagini editoriali dell'app.
            'welcome_message' => ['nullable', 'string', 'max:120'],
            'home_title' => ['nullable', 'string', 'max:60'],
            'home_subtitle' => ['nullable', 'string', 'max:160'],
            'primary_cta_label' => ['nullable', 'string', 'max:30'],
            'empty_appointments' => ['nullable', 'string', 'max:120'],
            'hero_image_url' => ['nullable', 'url:https', 'max:255'],
            // Push Notifications (Fase 7): colore accent + priorità.
            'notification_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'notification_priority' => ['sometimes', 'in:high,normal'],
            'privacy_policy_url' => ['nullable', 'url:https', 'max:255'],
            'terms_url' => ['nullable', 'url:https', 'max:255'],
            'support_url' => ['nullable', 'url:https', 'max:255'],
            // Contatti & social mostrati nell'app (scheda attività premium).
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url:https', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'],
            'whatsapp_message' => ['nullable', 'string', 'max:255'],
            'instagram_url' => ['nullable', 'url:https', 'max:255'],
            'facebook_url' => ['nullable', 'url:https', 'max:255'],
            'tiktok_url' => ['nullable', 'url:https', 'max:255'],
            'maps_url' => ['nullable', 'url:https', 'max:255'],
            // Anagrafica business (Fase 3): cookie policy + P.IVA.
            'cookie_url' => ['nullable', 'url:https', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:32'],
        ], [], ['app_name' => 'nome app', 'primary_color' => 'colore primario']);

        $brand = BrandProfile::query()->firstOrFail();

        $theme = $this->applyThemeInputs($brand->theme, $data, $darkPalette);
        $content = $this->collectContent($data);
        $notification = $this->collectNotification($data);

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
            'content' => $content,
            'notification' => $notification,
            'privacy_policy_url' => $data['privacy_policy_url'] ?? null,
            'terms_url' => $data['terms_url'] ?? null,
            'support_url' => $data['support_url'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'whatsapp_number' => $data['whatsapp_number'] ?? null,
            'whatsapp_message' => $data['whatsapp_message'] ?? null,
            'instagram_url' => $data['instagram_url'] ?? null,
            'facebook_url' => $data['facebook_url'] ?? null,
            'tiktok_url' => $data['tiktok_url'] ?? null,
            'maps_url' => $data['maps_url'] ?? null,
            'cookie_url' => $data['cookie_url'] ?? null,
            'vat_number' => $data['vat_number'] ?? null,
            'contrast_validated' => true,
        ]);
        $brand->bumpConfigVersion();

        $registry->forget($tenant->id());
        $audit->log('brand.updated', $request->user()->id, ['via' => 'dashboard']);

        return back()->with('status', 'Personalizzazione salvata: l\'app si aggiorna alla prossima apertura.');
    }

    /**
     * Applica gli input di tema del form sul tema salvato (Fase 1 — tema
     * premium). Il preset "style" mappa forma (raggi) e ombra in un unico
     * controllo. Il dark, in questa UI gestita, viene sempre riderivato dalla
     * palette light corrente (contrasto garantito) così resta coerente col
     * brand anche dopo un cambio colori — l'editor avanzato (API) resta libero
     * di fornire una palette dark su misura.
     *
     * @param  array<string, mixed>  $theme  tema corrente
     * @param  array<string, mixed>  $data   input validati
     * @return array<string, mixed>
     */
    private function applyThemeInputs(array $theme, array $data, DeriveDarkPalette $darkPalette): array
    {
        $theme['colors']['primary'] = $data['primary_color'];
        $theme['colors']['secondary'] = $data['secondary_color'];

        $optional = [
            'accent' => 'accent_color',
            'background' => 'background_color',
            'success' => 'success_color',
            'warning' => 'warning_color',
        ];

        foreach ($optional as $token => $field) {
            if (! empty($data[$field])) {
                $theme['colors'][$token] = $data[$field];
            }
        }

        if (isset($data['style'])) {
            $theme['radius'] = $data['style'] === 'flat'
                ? ['small' => 4, 'medium' => 6, 'large' => 10]
                : ['small' => 8, 'medium' => 12, 'large' => 24];
            $theme['elevation']['level'] = $data['style'] === 'flat' ? 0 : 1;
        }

        if (isset($data['shadow_level'])) {
            $theme['elevation']['level'] = (int) $data['shadow_level'];
        }

        if (isset($data['density'])) {
            $theme['density'] = $data['density'];
        }

        $mode = $data['mode'] ?? ($theme['mode'] ?? 'light');
        $theme['mode'] = $mode;

        if ($mode === 'light') {
            unset($theme['dark']);
        } else {
            $theme['dark']['colors'] = $darkPalette->fromLight($theme['colors']);
        }

        return $theme;
    }

    /**
     * Raccoglie le copy editoriali (Fase 5): solo i valori non vuoti; i campi
     * lasciati vuoti tornano al default di piattaforma (config/branding.php).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function collectContent(array $data): array
    {
        $keys = [
            'welcome_message',
            'home_title',
            'home_subtitle',
            'primary_cta_label',
            'empty_appointments',
            'hero_image_url',
        ];

        $content = [];

        foreach ($keys as $key) {
            $value = trim((string) ($data[$key] ?? ''));

            if ($value !== '') {
                $content[$key] = $value;
            }
        }

        return $content;
    }

    /**
     * Stile push (Fase 7): colore accent (vuoto = primary del brand) e
     * priorità. Solo valori esplicitamente scelti; il resto usa i default.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function collectNotification(array $data): array
    {
        $notification = [];

        if (! empty($data['notification_color'])) {
            $notification['color'] = $data['notification_color'];
        }

        if (! empty($data['notification_priority'])) {
            $notification['priority'] = $data['notification_priority'];
        }

        return $notification;
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
            // Coordinate GPS (Fase 3): pin preciso su Google Maps nell'app.
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ], [], ['name' => 'nome sede']);

        Location::query()->orderBy('id')->firstOrFail()->update($data);

        // Le sedi viaggiano nel config: bump per invalidare l'ETag client.
        $brand = BrandProfile::query()->firstOrFail();
        $brand->bumpConfigVersion();
        $registry->forget($tenant->id());

        return back()->with('status', 'Contatti aggiornati.');
    }

    /**
     * Assets (Fase 8): upload self-service del logo dalla dashboard, riusando
     * StoreBrandLogo (valida + salva + bump config_version) e GenerateBrandAssets
     * (deriva icone/splash/favicon). Il logo_url si aggiorna a runtime; le icone
     * native entrano in app alla prossima build (Smart Build — Fase 9).
     */
    public function uploadLogo(
        Request $request,
        StoreBrandLogo $storeLogo,
        GenerateBrandAssets $generateAssets,
        TenantRegistry $registry,
        CurrentTenant $tenant,
        AuditLogger $audit,
    ): RedirectResponse {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:8192'],
        ]);

        $brand = BrandProfile::query()->firstOrFail();
        $storeLogo->store($brand, $request->file('logo'));
        $generateAssets->execute($tenant->id());

        $registry->forget($tenant->id());
        $audit->log('brand.logo_uploaded', $request->user()->id, ['via' => 'dashboard']);

        return back()->with('status', 'Logo caricato: icone e splash rigenerate. Le icone native entrano con la prossima build.');
    }
}
