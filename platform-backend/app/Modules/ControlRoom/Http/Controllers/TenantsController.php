<?php

declare(strict_types=1);

namespace App\Modules\ControlRoom\Http\Controllers;

use App\Foundation\Enums\UserType;
use App\Foundation\Http\ApiException;
use App\Foundation\Tenancy\CurrentTenant;
use App\Models\User;
use App\Modules\AppFactory\Application\AllocateAppIdentifiers;
use App\Modules\AppFactory\Domain\TemplateRegistry;
use App\Modules\Branding\Infrastructure\Models\BrandAsset;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\TenantManagement\Application\ChangeTenantStatus;
use App\Modules\TenantManagement\Application\ProvisionTenant;
use App\Modules\TenantManagement\Domain\TenantStatus;
use App\Modules\TenantManagement\Infrastructure\Models\Plan;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Gestione tenant dalla Control Room. La creazione usa ESCLUSIVAMENTE
 * ProvisionTenant (nessuna logica duplicata); le transizioni di stato usano
 * ChangeTenantStatus (condivisa con l'API super-admin). Le letture dei
 * modelli tenant-scoped passano da CurrentTenant::bypass (super-admin
 * platform-level, nessun tenant legato alla sessione).
 */
final class TenantsController extends Controller
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        [$tenants, $owners] = $this->currentTenant->bypass(function () use ($q, $status): array {
            $tenants = Tenant::query()
                ->with('activeSubscription.plan')
                ->when($status !== '', fn (Builder $query) => $this->filterByStatus($query, $status))
                ->when($q !== '', fn (Builder $query) => $query->where(
                    fn (Builder $w) => $w->where('display_name', 'like', "%{$q}%")
                        ->orWhere('legal_name', 'like', "%{$q}%")
                ))
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString();

            $owners = User::query()
                ->whereIn('tenant_id', collect($tenants->items())->pluck('id'))
                ->where('type', UserType::TenantAdmin->value)
                ->get()
                ->keyBy('tenant_id');

            return [$tenants, $owners];
        });

        return view('control_room.tenants.index', compact('tenants', 'owners', 'q', 'status'));
    }

    public function create(TemplateRegistry $templates): View
    {
        $plans = Plan::query()->where('is_active', true)->orderBy('price_monthly_cents')->get();

        return view('control_room.tenants.create', [
            'plans' => $plans,
            'templates' => $templates->all(),
        ]);
    }

    public function store(Request $request, ProvisionTenant $provision, AllocateAppIdentifiers $allocate): RedirectResponse
    {
        $data = $request->validate([
            'display_name' => ['required', 'string', 'min:2', 'max:30'],
            'sector' => ['required', 'in:barber,hair,beauty,dental,medical,physio,consultant,other'],
            'admin_email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'plan_code' => ['required', 'string', 'exists:plans,code'],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'template_code' => ['nullable', 'string'],
            'health_data' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $provision->execute([
                'legal_name' => $data['display_name'],
                'display_name' => $data['display_name'],
                'sector' => $data['sector'],
                'timezone' => 'Europe/Rome',
                'locale' => 'it',
                'plan_code' => $data['plan_code'],
                'app_name' => $data['display_name'],
                'admin_email' => $data['admin_email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'health_data' => (bool) ($data['health_data'] ?? false),
            ], $request->user('admin')->id);
        } catch (ApiException $e) {
            return back()->withInput()->withErrors(['display_name' => $this->humanize($e)]);
        }

        $tenant = $result['tenant'];

        if (! empty($data['primary_color'])) {
            $this->applyPrimaryColor($tenant->id, $data['primary_color']);
        }

        // Ogni cliente è un'app: alloca subito l'identità store/build (App Project).
        $allocate->execute($tenant, $data['template_code'] ?? 'default', $request->user('admin')->id);

        return redirect()->route('control.tenants.show', $tenant->uuid)
            ->with('status', 'Cliente creato. Invia il link di accesso al titolare.')
            ->with('invite_link', route('dashboard.invite', [
                'email' => $result['admin']->email,
                'token' => $result['invite_token'],
            ]))
            ->with('api_key', $tenant->api_key);
    }

    public function show(string $uuid): View
    {
        $data = $this->currentTenant->bypass(function () use ($uuid): array {
            $tenant = Tenant::query()->with('activeSubscription.plan')->where('uuid', $uuid)->firstOrFail();

            $owner = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('type', UserType::TenantAdmin->value)
                ->first();

            $brand = BrandProfile::query()->where('tenant_id', $tenant->id)->first();

            $logo = $brand === null ? null : BrandAsset::query()
                ->where('brand_profile_id', $brand->id)
                ->where('kind', BrandAsset::KIND_LOGO)
                ->first();

            $inviteRow = $owner === null ? null : DB::table('password_reset_tokens')
                ->where('email', $owner->email)
                ->first();

            return compact('tenant', 'owner', 'brand', 'logo', 'inviteRow');
        });

        $data['inviteStatus'] = $this->inviteStatus($data['owner'], $data['inviteRow']);

        return view('control_room.tenants.show', $data);
    }

    public function suspend(Request $request, string $uuid): RedirectResponse
    {
        return $this->transition($request, $uuid, TenantStatus::Suspended, 'tenant.suspended', 'Cliente sospeso.');
    }

    public function reactivate(Request $request, string $uuid): RedirectResponse
    {
        return $this->transition($request, $uuid, TenantStatus::Active, 'tenant.reactivated', 'Cliente riattivato.');
    }

    public function activate(Request $request, string $uuid): RedirectResponse
    {
        return $this->transition($request, $uuid, TenantStatus::Active, 'tenant.activated', 'Cliente attivato.');
    }

    private function transition(Request $request, string $uuid, TenantStatus $target, string $action, string $message): RedirectResponse
    {
        try {
            app(ChangeTenantStatus::class)->execute($uuid, $target, $action, $request->user('admin')->id);
        } catch (ApiException $e) {
            return back()->with('error', $this->humanize($e));
        }

        return back()->with('status', $message);
    }

    private function applyPrimaryColor(int $tenantId, string $color): void
    {
        $this->currentTenant->bypass(function () use ($tenantId, $color): void {
            $brand = BrandProfile::query()->where('tenant_id', $tenantId)->first();

            if ($brand === null) {
                return;
            }

            $theme = $brand->theme;
            $theme['colors']['primary'] = $color;

            $brand->forceFill([
                'primary_color' => $color,
                'theme' => $theme,
                'config_version' => $brand->config_version + 1,
            ])->save();
        });
    }

    private function filterByStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'active' => $query->whereIn('status', ['active', 'at_risk']),
            'suspended' => $query->where('status', 'suspended'),
            'pending' => $query->where('status', 'onboarding'),
            'archived' => $query->where('status', 'terminated'),
            default => $query,
        };
    }

    private function inviteStatus(?User $owner, ?object $inviteRow): string
    {
        if ($owner === null) {
            return 'none';
        }

        if ($owner->password !== null) {
            return 'accepted';
        }

        if ($inviteRow === null) {
            return 'none';
        }

        return now()->diffInHours(Carbon::parse($inviteRow->created_at), true) < 72 ? 'pending' : 'expired';
    }

    private function humanize(ApiException $e): string
    {
        return match ($e->errorCode) {
            'health_module_required' => 'I settori sanitari (dentista, medico, fisioterapista) richiedono l\'attivazione del modulo dati sanitari.',
            'invalid_tenant_transition' => 'Operazione non consentita dallo stato attuale del cliente.',
            'unknown_plan' => 'Piano selezionato non valido.',
            default => $e->getMessage(),
        };
    }
}
