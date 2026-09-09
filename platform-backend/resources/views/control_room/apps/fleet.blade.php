@extends('control_room.layout')
@section('title', 'Flotta')
@section('content')

<div class="card">
    <h2 style="margin-top:0">Release train</h2>
    <p class="muted" style="margin-top:0">Core corrente: <code>{{ $current_core }}</code>. Le app costruite con un core precedente sono <em>stale</em> e vanno ricostruite a lotti.</p>
    <div style="display:flex; gap:24px; flex-wrap:wrap">
        <div><div style="font-size:28px; font-weight:700">{{ $buildable }}</div><div class="muted">costruibili</div></div>
        <div><div style="font-size:28px; font-weight:700; color:#15803d">{{ $on_current }}</div><div class="muted">allineate al core</div></div>
        <div><div style="font-size:28px; font-weight:700; color:{{ $stale > 0 ? '#b45309' : 'inherit' }}">{{ $stale }}</div><div class="muted">stale (da ricostruire)</div></div>
    </div>
</div>

<div class="card">
    <h3 style="margin-top:0">Build native</h3>
    <div style="display:flex; gap:24px; flex-wrap:wrap">
        <div><div style="font-size:28px; font-weight:700; color:#15803d">{{ $builds['succeeded'] }}</div><div class="muted">riuscite</div></div>
        <div><div style="font-size:28px; font-weight:700; color:{{ $builds['failed'] > 0 ? '#dc2626' : 'inherit' }}">{{ $builds['failed'] }}</div><div class="muted">fallite</div></div>
        <div><div style="font-size:28px; font-weight:700; color:{{ $builds['in_progress'] > 0 ? '#b45309' : 'inherit' }}">{{ $builds['in_progress'] }}</div><div class="muted">in corso</div></div>
        <div><div style="font-size:28px; font-weight:700; color:#15803d">{{ $beta_active }}</div><div class="muted">beta attive</div></div>
    </div>
</div>

<div class="card">
    <h3 style="margin-top:0">Stato build (tutte le app)</h3>
    @php
        $labels = [
            'draft' => 'Bozza', 'configured' => 'Configurata', 'ready' => 'Pronta', 'generated' => 'Generata',
            'ready_to_build' => 'Pronta build', 'building' => 'In build', 'built' => 'Compilata',
            'published' => 'Pubblicata', 'failed' => 'Fallita',
        ];
    @endphp
    @if (empty($by_status))
        <div class="empty">Nessuna app ancora.</div>
    @else
        <table>
            <tr><th>Stato</th><th>App</th></tr>
            @foreach ($by_status as $status => $count)
                <tr><td>{{ $labels[$status] ?? $status }}</td><td><strong>{{ $count }}</strong></td></tr>
            @endforeach
        </table>
    @endif
</div>

<div class="card">
    <h3 style="margin-top:0">Build recenti</h3>
    @if ($recent->isEmpty())
        <div class="empty">Nessuna build registrata.</div>
    @else
        <table>
            <tr><th>Attività</th><th>Versione</th><th>Piattaforma</th><th>Stato</th><th>Quando</th></tr>
            @foreach ($recent as $build)
                @php $bc = ['failed' => 'danger', 'building' => 'warn', 'published' => 'ok', 'built' => 'ok'][$build->status] ?? 'off'; @endphp
                <tr>
                    <td><strong>{{ $tenants[$build->tenant_id]?->display_name ?? '—' }}</strong></td>
                    <td><code>{{ $build->version }}</code></td>
                    <td class="muted">{{ $build->platform }}</td>
                    <td><span class="badge {{ $bc }}">{{ $build->status }}</span></td>
                    <td class="muted">{{ $build->created_at?->format('d/m/Y H:i') }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

<div class="card">
    <h3 style="margin-top:0">Ricostruisci la flotta</h3>
    <p class="muted" style="margin-top:0">
        Accoda una build per ogni app <strong>stale</strong> ({{ $stale }} oggi) — nessun terminale richiesto.
        Le app senza manifest ancora generato vengono saltate e segnalate, non bloccano le altre.
    </p>
    <form method="post" action="{{ route('control.apps.fleet.rebuild') }}"
          onsubmit="return confirm('Accodare la build per le app stale? L\'operazione può richiedere tempo se ce ne sono molte.')">
        @csrf
        <div class="row" style="align-items:end">
            <div>
                <label for="platform">Piattaforma</label>
                <select id="platform" name="platform">
                    <option value="android">Android</option>
                    <option value="ios">iOS</option>
                </select>
            </div>
            <div>
                <label for="limit">Limite (canary, opzionale)</label>
                <input id="limit" type="number" name="limit" min="1" max="100" placeholder="es. 1 per un canary">
            </div>
            <div><button class="btn" type="submit" @disabled($stale === 0)>Ricostruisci flotta stale</button></div>
        </div>
    </form>
    <p class="muted mt" style="font-size:13px">
        In alternativa, per lotti molto grandi resta disponibile la CI (workflow <code>app-factory-batch</code>,
        <code>php artisan app:build-matrix --stale-only</code>) — vedi <code>docs/AppFactory/</code>.
    </p>
</div>

@endsection
