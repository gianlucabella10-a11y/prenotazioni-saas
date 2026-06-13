@extends('dashboard.layout')
@section('title', 'Home')
@section('content')

<div class="grid cols-3">
    <div class="card stat">
        <div class="n">{{ $today->count() }}</div>
        <div class="l">Appuntamenti oggi</div>
    </div>
    <div class="card stat">
        <div class="n">{{ $pendingCount }}</div>
        <div class="l">In attesa di conferma</div>
        @if ($pendingCount > 0)
            <a class="btn small mt" href="{{ route('dashboard.bookings.index') }}#in-attesa">Gestisci</a>
        @endif
    </div>
    <div class="card stat">
        <div class="n">{{ $upcomingWeek }}</div>
        <div class="l">Prossimi 7 giorni</div>
    </div>
</div>

<div class="card">
    <h2>Oggi</h2>
    @if ($today->isEmpty())
        <div class="empty">Nessun appuntamento per oggi.</div>
    @else
        <table>
            <tr><th>Ora</th><th>Cliente</th><th>Servizio</th><th>Operatore</th><th>Stato</th></tr>
            @foreach ($today as $appointment)
                <tr>
                    <td><strong>{{ $appointment->starts_at->setTimezone($timezone)->format('H:i') }}</strong></td>
                    <td>{{ $appointment->customer->fullName() }}</td>
                    <td>{{ $appointment->items->pluck('service_name_snapshot')->implode(' + ') }}</td>
                    <td>{{ $appointment->items->first()?->staffMember?->display_name }}</td>
                    <td>
                        @if ($appointment->status->value === 'requested')
                            <span class="badge warn">In attesa</span>
                        @else
                            <span class="badge ok">Confermato</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

@if ($isOwner)
    <div class="grid cols-2">
        <div class="card">
            <h2>Servizi più richiesti (30 giorni)</h2>
            @if ($topServices->isEmpty())
                <div class="empty">Ancora nessun dato.</div>
            @else
                <table>
                    @foreach ($topServices as $row)
                        <tr><td>{{ $row->service_name_snapshot }}</td>
                            <td style="text-align:right"><strong>{{ $row->total }}</strong></td></tr>
                    @endforeach
                </table>
            @endif
        </div>
        <div class="card stat">
            <div class="n">{{ $activeStaffCount }}</div>
            <div class="l">Operatori attivi</div>
            <a class="btn small secondary mt" href="{{ route('dashboard.staff.index') }}">Gestisci operatori</a>
        </div>
    </div>
@endif

@endsection
