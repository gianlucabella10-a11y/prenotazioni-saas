@extends('control_room.layout')
@section('title', 'Cruscotto')
@section('content')

@if (count($alerts) > 0)
    <div class="card">
        <h2>Cosa devo fare adesso?</h2>
        @foreach ($alerts as $alert)
            <div class="flash {{ $alert['level'] === 'danger' ? 'err' : 'warn' }}" style="margin-bottom:8px">
                {{ $alert['message'] }}
                @if ($alert['actionRoute'])
                    <a href="{{ route($alert['actionRoute']) }}" class="btn small" style="margin-left:8px">{{ $alert['actionLabel'] }}</a>
                @endif
            </div>
        @endforeach
    </div>
@else
    <div class="card">
        <h2>Cosa devo fare adesso?</h2>
        <p class="muted" style="margin-top:0">✅ Nessuna segnalazione. La piattaforma è in stato regolare.</p>
    </div>
@endif

<div class="grid cols-2">
    <div class="card">
        <h2>Clienti</h2>
        <table>
            <tr><td class="muted">Attivi</td><td><strong>{{ ($tenantCounts['active'] ?? 0) + ($tenantCounts['at_risk'] ?? 0) }}</strong></td></tr>
            <tr><td class="muted">In attivazione</td><td>{{ $tenantCounts['onboarding'] ?? 0 }}</td></tr>
            <tr><td class="muted">Sospesi</td><td>{{ $tenantCounts['suspended'] ?? 0 }}</td></tr>
            <tr><td class="muted">Archiviati</td><td>{{ $tenantCounts['terminated'] ?? 0 }}</td></tr>
            <tr><td class="muted">Abbonamenti in scadenza (7gg)</td><td>{{ $renewals['dueSoon'] }}</td></tr>
            <tr><td class="muted">Abbonamenti scaduti</td><td>{{ $renewals['expired'] }}</td></tr>
        </table>
        <a class="btn small mt" href="{{ route('control.tenants.create') }}">+ Nuovo cliente</a>
    </div>

    <div class="card">
        <h2>Sistema</h2>
        <table>
            <tr><td class="muted">Job in coda</td><td>{{ $queue['pending'] }}</td></tr>
            <tr><td class="muted">Job falliti</td><td>{{ $queue['failed'] }}</td></tr>
            <tr><td class="muted">Spazio disco libero</td><td>{{ $disk['freePercent'] }}%</td></tr>
            <tr><td class="muted">Ultimo backup</td>
                <td>@if ($backup['filename']) {{ $backup['daysAgo'] }} giorni fa @else mai @endif</td></tr>
        </table>
        <a class="btn small secondary mt" href="{{ route('control.logs.index') }}">Vedi log</a>
    </div>
</div>

<div class="grid cols-2">
    <div class="card">
        <h2>Ultimi clienti</h2>
        @if ($recentTenants->isEmpty())
            <div class="empty">Nessun cliente ancora.</div>
        @else
            <table>
                <tr><th>Attività</th><th>Stato</th><th></th></tr>
                @foreach ($recentTenants as $t)
                    <tr>
                        <td>{{ $t->display_name }}</td>
                        <td>@include('control_room.tenants._status', ['status' => $t->status])</td>
                        <td><a class="btn small secondary" href="{{ route('control.tenants.show', $t->uuid) }}">Apri</a></td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>

    <div class="card">
        <h2>Ultime build</h2>
        @if ($recentBuilds->isEmpty())
            <div class="empty">Nessuna build ancora.</div>
        @else
            <table>
                <tr><th>Cliente</th><th>Versione</th><th>Stato</th></tr>
                @foreach ($recentBuilds as $b)
                    <tr>
                        <td>{{ $tenantNames[$b->appProject?->tenant_id] ?? '—' }}</td>
                        <td class="muted">{{ $b->version }}</td>
                        <td><span class="badge {{ $b->status === 'built' ? 'ok' : ($b->status === 'failed' ? 'danger' : 'warn') }}">{{ $b->status }}</span></td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
</div>

@endsection
