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
    <h3 style="margin-top:0">Stato build (tutte le app)</h3>
    @php
        $labels = [
            'draft' => 'Bozza', 'ready' => 'Pronta', 'generated' => 'Generata',
            'ready_to_build' => 'Pronta build', 'building' => 'In build',
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
    <h3 style="margin-top:0">Costruire la flotta</h3>
    <p class="muted">La build a lotti è lanciata dalla CI (workflow <code>app-factory-batch</code>), che legge la matrice dal backend:</p>
    <pre style="overflow:auto"><code>php artisan app:build-matrix --platform=android --stale-only --limit=10</code></pre>
    <p class="muted">Canary: parti con <code>--limit=1</code>, verifica, poi rilancia senza limite. Vedi <code>APP_FACTORY_PHASE3_READINESS.md</code>.</p>
</div>

@endsection
