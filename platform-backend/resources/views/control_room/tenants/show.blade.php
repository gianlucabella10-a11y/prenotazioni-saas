@extends('control_room.layout')
@section('title', $tenant->display_name)
@section('content')

@if (session('invite_link'))
    <div class="card" style="border-color:var(--secondary)">
        <h2>🔑 Link di accesso per il titolare</h2>
        <p class="muted" style="margin-top:0">
            Invialo al titolare: lo userà per impostare la password al primo accesso. Visibile solo ora.
        </p>
        <code class="copy">{{ session('invite_link') }}</code>
        @if (session('api_key'))
            <p class="muted mt" style="font-size:13px">API key tenant: <code>{{ session('api_key') }}</code></p>
        @endif
    </div>
@endif

<div class="grid cols-2">
    {{-- Informazioni + operazioni --}}
    <div class="card">
        <h2>Informazioni</h2>
        <table>
            <tr><td class="muted">Attività</td><td><strong>{{ $tenant->display_name }}</strong></td></tr>
            <tr><td class="muted">Categoria</td><td>{{ ucfirst($tenant->sector) }}</td></tr>
            <tr><td class="muted">Titolare</td><td>{{ $owner?->email ?? '—' }}</td></tr>
            <tr><td class="muted">Stato</td><td>@include('control_room.tenants._status', ['status' => $tenant->status])</td></tr>
            <tr><td class="muted">Piano</td><td>{{ $tenant->activeSubscription?->plan?->name ?? '—' }}</td></tr>
            <tr><td class="muted">Creato</td><td>{{ $tenant->created_at?->format('d/m/Y H:i') }}</td></tr>
        </table>

        <h2 class="mt">Operazioni</h2>
        <div style="display:flex; gap:8px; flex-wrap:wrap">
            @if ($tenant->status->value === 'onboarding')
                <form method="post" action="{{ route('control.tenants.activate', $tenant->uuid) }}">
                    @csrf <button class="btn small" type="submit">Attiva</button>
                </form>
            @endif
            @if (in_array($tenant->status->value, ['active', 'at_risk'], true))
                <form method="post" action="{{ route('control.tenants.suspend', $tenant->uuid) }}"
                      onsubmit="return confirm('Sospendere questo cliente? Le prenotazioni verranno bloccate.')">
                    @csrf <button class="btn small danger" type="submit">Sospendi</button>
                </form>
            @endif
            @if ($tenant->status->value === 'suspended')
                <form method="post" action="{{ route('control.tenants.reactivate', $tenant->uuid) }}">
                    @csrf <button class="btn small" type="submit">Riattiva</button>
                </form>
            @endif
            @if ($tenant->status->value !== 'terminated')
                <form method="post" action="{{ route('control.tenants.terminate', $tenant->uuid) }}"
                      onsubmit="return confirm('Archiviare definitivamente questo cliente? Non è reversibile.')">
                    @csrf <button class="btn small danger" type="submit">Archivia</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Accessi / invito --}}
    <div class="card">
        <h2>Accessi</h2>
        @php $m = ['pending' => ['warn', 'In attesa'], 'accepted' => ['ok', 'Accettato'], 'expired' => ['danger', 'Scaduto'], 'none' => ['off', 'Nessuno']]; [$c, $l] = $m[$inviteStatus]; @endphp
        <p>Stato invito titolare: <span class="badge {{ $c }}">{{ $l }}</span></p>
        <div style="display:flex; gap:8px; flex-wrap:wrap">
            <form method="post" action="{{ route('control.tenants.invite.regenerate', $tenant->uuid) }}">
                @csrf <button class="btn small" type="submit">Rigenera link</button>
            </form>
            @if ($inviteStatus === 'pending' || $inviteStatus === 'expired')
                <form method="post" action="{{ route('control.tenants.invite.revoke', $tenant->uuid) }}"
                      onsubmit="return confirm('Revocare l invito in sospeso?')">
                    @csrf <button class="btn small secondary" type="submit">Revoca</button>
                </form>
            @endif
        </div>
        <p class="muted mt" style="font-size:13px">
            Per sicurezza il link completo è mostrato solo al momento della (ri)generazione.
        </p>
    </div>
</div>

{{-- White Label Quick Setup --}}
<div class="card">
    <h2>Brand</h2>
    <div class="row" style="align-items:flex-start">
        <form method="post" action="{{ route('control.tenants.brand', $tenant->uuid) }}" style="flex:2">
            @csrf @method('PUT')
            <label>Nome app</label>
            <input name="app_name" required maxlength="30" value="{{ old('app_name', $brand?->app_name ?? $tenant->display_name) }}">
            <div class="row">
                <div>
                    <label>Colore principale</label>
                    <input type="color" name="primary_color" value="{{ old('primary_color', $brand?->primary_color ?? '#1F2937') }}">
                </div>
                <div>
                    <label>Colore accento</label>
                    <input type="color" name="secondary_color" value="{{ old('secondary_color', $brand?->secondary_color ?? '#C8A24B') }}">
                </div>
            </div>
            <button class="btn mt" type="submit">Salva brand</button>
        </form>

        <form method="post" action="{{ route('control.tenants.logo', $tenant->uuid) }}" enctype="multipart/form-data" style="flex:1">
            @csrf
            <label>Logo</label>
            @if ($logo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('branding.asset_disk', 'public'))->url($logo->disk_path) }}"
                     alt="logo" style="max-width:120px; max-height:120px; display:block; margin-bottom:8px;
                     border:1px solid var(--border); border-radius:8px; padding:6px; background:#fff">
            @else
                <div class="muted" style="font-size:13px; margin-bottom:8px">Nessun logo caricato.</div>
            @endif
            <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg" required>
            <button class="btn small mt" type="submit">Carica logo</button>
        </form>
    </div>
</div>

{{-- Tecnico --}}
<div class="card">
    <h2>Tecnico</h2>
    <table>
        <tr><td class="muted">Tenant UUID</td><td><code>{{ $tenant->uuid }}</code></td></tr>
        <tr><td class="muted">API key</td><td><code>{{ $tenant->api_key }}</code></td></tr>
        <tr><td class="muted">Timezone</td><td>{{ $tenant->default_timezone }}</td></tr>
        <tr><td class="muted">Sottoscrizione</td><td>{{ $tenant->activeSubscription?->status ?? '—' }}</td></tr>
    </table>
</div>

@endsection
