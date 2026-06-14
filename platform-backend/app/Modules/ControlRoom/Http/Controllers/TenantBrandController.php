<?php

declare(strict_types=1);

namespace App\Modules\ControlRoom\Http\Controllers;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\Branding\Application\StoreBrandLogo;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * White Label Quick Setup dalla Control Room: nome app, colori e logo del
 * tenant. Scrive su brand_profiles / brand_assets (modelli tenant-scoped)
 * via CurrentTenant::bypass; ogni modifica incrementa config_version per il
 * cache-busting del client.
 */
final class TenantBrandController extends Controller
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function update(Request $request, string $uuid, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'min:2', 'max:30'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $this->currentTenant->bypass(function () use ($uuid, $data, $request, $audit): void {
            $tenant = Tenant::query()->where('uuid', $uuid)->firstOrFail();
            $brand = BrandProfile::query()->where('tenant_id', $tenant->id)->firstOrFail();

            $theme = $brand->theme;
            $theme['colors']['primary'] = $data['primary_color'];
            $theme['colors']['secondary'] = $data['secondary_color'];

            $brand->forceFill([
                'app_name' => $data['app_name'],
                'primary_color' => $data['primary_color'],
                'secondary_color' => $data['secondary_color'],
                'theme' => $theme,
                'config_version' => $brand->config_version + 1,
            ])->save();

            $audit->log('control_room.brand_updated', $request->user('admin')->id, [], $tenant->id);
        });

        return back()->with('status', "Brand aggiornato: le modifiche arrivano sull'app alla prossima apertura.");
    }

    public function uploadLogo(Request $request, string $uuid, StoreBrandLogo $storeLogo, AuditLogger $audit): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ]);

        $this->currentTenant->bypass(function () use ($uuid, $request, $storeLogo, $audit): void {
            $tenant = Tenant::query()->where('uuid', $uuid)->firstOrFail();
            $brand = BrandProfile::query()->where('tenant_id', $tenant->id)->firstOrFail();

            $storeLogo->store($brand, $request->file('logo'));

            $audit->log('control_room.logo_uploaded', $request->user('admin')->id, [], $tenant->id);
        });

        return back()->with('status', 'Logo caricato.');
    }
}
