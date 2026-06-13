<?php

declare(strict_types=1);

namespace App\Modules\Branding\Presentation\Controllers;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Http\ApiException;
use App\Foundation\Tenancy\CurrentTenant;
use App\Foundation\Tenancy\TenantRegistry;
use App\Modules\Branding\Application\ContrastValidator;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Brand Studio API (docs/27 §2, §8): theme/name updates take effect at
 * runtime through config_version bumps — no rebuild, no store review.
 * Palette changes are gated by the WCAG contrast validator (RF-12).
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
            'theme.colors' => ['required_with:theme', 'array'],
            'theme.colors.*' => ['regex:/^#[0-9A-Fa-f]{6}$/'],
            // Legal/support links (https only: they ship in store metadata).
            'privacy_policy_url' => ['nullable', 'url:https', 'max:255'],
            'terms_url' => ['nullable', 'url:https', 'max:255'],
            'support_url' => ['nullable', 'url:https', 'max:255'],
        ]);

        $candidateTheme = $data['theme'] ?? $brand->theme;

        if (isset($data['primary_color'])) {
            $candidateTheme['colors']['primary'] = $data['primary_color'];
        }

        if (isset($data['secondary_color'])) {
            $candidateTheme['colors']['secondary'] = $data['secondary_color'];
        }

        $this->assertReadableContrast($contrast, $candidateTheme);

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

    /** @param array{colors?: array<string, string>} $theme */
    private function assertReadableContrast(ContrastValidator $contrast, array $theme): void
    {
        $colors = $theme['colors'] ?? [];
        $minimum = (float) config('branding.minimum_contrast_ratio');

        $pairs = [
            ['primary', 'on_primary'],
            ['surface', 'on_surface'],
        ];

        foreach ($pairs as [$bg, $fg]) {
            if (isset($colors[$bg], $colors[$fg]) && ! $contrast->meetsMinimum($colors[$bg], $colors[$fg], $minimum)) {
                throw ApiException::unprocessable(
                    'insufficient_contrast',
                    "The {$fg}/{$bg} color pair does not meet the minimum contrast ratio of {$minimum}:1.",
                    ['pair' => [$bg, $fg]],
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
            'support_url' => $brand->support_url,
        ];
    }
}
