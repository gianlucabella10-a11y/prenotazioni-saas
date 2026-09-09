<?php

declare(strict_types=1);

namespace App\Modules\Branding\Presentation\Controllers;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Http\ApiException;
use App\Foundation\Tenancy\CurrentTenant;
use App\Foundation\Tenancy\TenantRegistry;
use App\Modules\Branding\Application\ContrastValidator;
use App\Modules\Branding\Application\DeriveDarkPalette;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Brand Studio API (docs/27 §2, §8): theme/name updates take effect at
 * runtime through config_version bumps — no rebuild, no store review.
 * Palette changes are gated by the WCAG contrast validator (RF-12), sia per la
 * palette light sia per quella dark (Fase 1 — Brand Identity: tema premium).
 */
final class ManageBrandController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->serialize(BrandProfile::query()->firstOrFail())]);
    }

    public function update(
        Request $request,
        ContrastValidator $contrast,
        DeriveDarkPalette $darkPalette,
        TenantRegistry $registry,
        CurrentTenant $currentTenant,
        AuditLogger $audit,
    ): JsonResponse {
        $brand = BrandProfile::query()->firstOrFail();

        $data = $request->validate([
            'app_name' => ['sometimes', 'string', 'min:2', 'max:30'],
            'tagline' => ['nullable', 'string', 'max:80'],
            'primary_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme' => ['sometimes', 'array'],
            // Modalità tema: guida themeMode nel client.
            'theme.mode' => ['sometimes', 'in:light,dark,system'],
            // Partial-friendly: mergeTheme() fonde sul tema salvato, quindi un
            // update di solo `mode` o di pochi colori non azzera il resto.
            'theme.colors' => ['sometimes', 'array'],
            'theme.colors.*' => ['regex:/^#[0-9A-Fa-f]{6}$/'],
            // Palette dark opzionale (se assente e mode≠light la deriviamo).
            'theme.dark' => ['sometimes', 'array'],
            'theme.dark.colors' => ['sometimes', 'array'],
            'theme.dark.colors.*' => ['regex:/^#[0-9A-Fa-f]{6}$/'],
            // Stile: raggi e livello ombra (Rounded/Flat + Shadow Level).
            'theme.radius' => ['sometimes', 'array'],
            'theme.radius.small' => ['sometimes', 'numeric', 'min:0', 'max:48'],
            'theme.radius.medium' => ['sometimes', 'numeric', 'min:0', 'max:48'],
            'theme.radius.large' => ['sometimes', 'numeric', 'min:0', 'max:48'],
            'theme.elevation' => ['sometimes', 'array'],
            'theme.elevation.level' => ['sometimes', 'numeric', 'min:0', 'max:4'],
            // App Identity: densità visiva dell'intera app.
            'theme.density' => ['sometimes', 'in:comfortable,standard,compact'],
            'theme.typography' => ['sometimes', 'array'],
            'theme.typography.scale' => ['sometimes', 'numeric', 'min:0.8', 'max:1.4'],
            // Legal/support links (https only: they ship in store metadata).
            'privacy_policy_url' => ['nullable', 'url:https', 'max:255'],
            'terms_url' => ['nullable', 'url:https', 'max:255'],
            'cookie_url' => ['nullable', 'url:https', 'max:255'],
            'support_url' => ['nullable', 'url:https', 'max:255'],
        ]);

        // Partiamo dal tema salvato e sovrascriviamo solo ciò che arriva:
        // così un update parziale (solo mode, solo un colore) non azzera il resto.
        $candidateTheme = $this->mergeTheme($brand->theme, $data['theme'] ?? []);

        if (isset($data['primary_color'])) {
            $candidateTheme['colors']['primary'] = $data['primary_color'];
        }

        if (isset($data['secondary_color'])) {
            $candidateTheme['colors']['secondary'] = $data['secondary_color'];
        }

        // Dark abilitato ma senza palette esplicita → derivala (contrasto ok).
        $mode = $candidateTheme['mode'] ?? 'light';
        if ($mode !== 'light' && empty($candidateTheme['dark']['colors'])) {
            $candidateTheme['dark']['colors'] = $darkPalette->fromLight($candidateTheme['colors']);
        }

        $this->assertReadableContrast($contrast, $candidateTheme['colors'] ?? [], 'light');

        if (! empty($candidateTheme['dark']['colors'])) {
            $this->assertReadableContrast($contrast, $candidateTheme['dark']['colors'], 'dark');
        }

        $brand->fill(collect($data)->except('theme')->all());
        $brand->theme = $candidateTheme;
        $brand->primary_color = $candidateTheme['colors']['primary'];
        $brand->secondary_color = $candidateTheme['colors']['secondary'];
        $brand->contrast_validated = true;
        $brand->save();
        $brand->bumpConfigVersion();

        $registry->forget($currentTenant->id()); // config snapshot changed

        $audit->log('brand.updated', $request->user()?->id, ['fields' => array_keys($data)]);

        return response()->json(['data' => $this->serialize($brand->refresh())]);
    }

    /**
     * Deep-merge del tema in ingresso su quello salvato (una sola profondità
     * per le sotto-mappe note), così un update parziale non perde chiavi.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeTheme(array $current, array $incoming): array
    {
        foreach (['colors', 'radius', 'elevation', 'typography'] as $section) {
            if (isset($incoming[$section]) && is_array($incoming[$section])) {
                $incoming[$section] = [...($current[$section] ?? []), ...$incoming[$section]];
            }
        }

        if (isset($incoming['dark']['colors']) && is_array($incoming['dark']['colors'])) {
            $incoming['dark']['colors'] = [
                ...($current['dark']['colors'] ?? []),
                ...$incoming['dark']['colors'],
            ];
        }

        return [...$current, ...$incoming];
    }

    /**
     * @param  array<string, string>  $colors
     * @param  string  $variant  light|dark (per messaggi d'errore chiari)
     */
    private function assertReadableContrast(ContrastValidator $contrast, array $colors, string $variant): void
    {
        $minimum = (float) config('branding.minimum_contrast_ratio');

        $pairs = [
            ['primary', 'on_primary'],
            ['surface', 'on_surface'],
        ];

        foreach ($pairs as [$bg, $fg]) {
            if (isset($colors[$bg], $colors[$fg]) && ! $contrast->meetsMinimum($colors[$bg], $colors[$fg], $minimum)) {
                throw ApiException::unprocessable(
                    'insufficient_contrast',
                    "The {$fg}/{$bg} ({$variant}) color pair does not meet the minimum contrast ratio of {$minimum}:1.",
                    ['pair' => [$bg, $fg], 'variant' => $variant],
                );
            }
        }
    }

    /** @return array<string, mixed> */
    private function serialize(BrandProfile $brand): array
    {
        return [
            'app_name' => $brand->app_name,
            'tagline' => $brand->tagline,
            'primary_color' => $brand->primary_color,
            'secondary_color' => $brand->secondary_color,
            'theme' => $brand->theme,
            'config_version' => $brand->config_version,
            'contrast_validated' => $brand->contrast_validated,
            'privacy_policy_url' => $brand->privacy_policy_url,
            'terms_url' => $brand->terms_url,
            'cookie_url' => $brand->cookie_url,
            'support_url' => $brand->support_url,
        ];
    }
}
