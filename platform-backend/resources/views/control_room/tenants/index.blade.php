@extends('control_room.layout')
@section('title', 'Clienti')
@section('content')

<div class="card">
    <form method="get" action="{{ route('control.tenants.index') }}" class="row" style="align-items:end">
        <div>
            <label for="q">Cerca</label>
            <input id="q" type="text" name="q" value="{{ $q }}" placeholder="Nome attività">
        </div>
        <div>
            <label for="status">Stato</label>
            <select id="status" name="status" onchange="this.form.submit()">
                <option value="">Tutti</option>
                <option value="active" @selected($status === 'active')>Attivi</option>
                <option value="pending" @selected($status === 'pending')>In attivazione</option>
                <option value="suspended" @selected($status === 'suspended')>Sospesi</option>
                <option value="archived" @selected($status === 'archived')>Archiviati</option>
            </select>
        </div>
        <div><button class="btn" type="submit">Filtra</button></div>
        <div style="text-align:right"><a class="btn" href="{{ route('control.tenants.create') }}">+ Nuovo cliente</a></div>
    </form>
</div>

<div class="card">
    @if ($tenants->isEmpty())
        <div class="empty">Nessun cliente trovato. Creane uno con «+ Nuovo cliente».</div>
    @else
        <table>
            <tr>
                <th>Attività</th><th>Categoria</th><th>Titolare</th><th>Stato</th>
                <th>Piano</th><th>Creato</th><th>Ultimo agg.</th><th></th>
            </tr>
            @foreach ($tenants as $tenant)
                @php $owner = $owners[$tenant->id] ?? null; @endphp
                <tr>
                    <td><strong>{{ $tenant->display_name }}</strong></td>
                    <td class="muted">{{ ucfirst($tenant->sector) }}</td>
                    <td>{{ $owner?->email ?? '—' }}</td>
                    <td>@include('control_room.tenants._status', ['status' => $tenant->status])</td>
                    <td>{{ $tenant->activeSubscription?->plan?->name ?? '—' }}</td>
                    <td class="muted">{{ $tenant->created_at?->format('d/m/Y') }}</td>
                    <td class="muted">{{ $tenant->updated_at?->format('d/m/Y H:i') }}</td>
                    <td><a class="btn small secondary" href="{{ route('control.tenants.show', $tenant->uuid) }}">Apri</a></td>
                </tr>
            @endforeach
        </table>

        @if ($tenants->hasPages())
            <div class="mt" style="display:flex; gap:8px">
                @if ($tenants->previousPageUrl())
                    <a class="btn small secondary" href="{{ $tenants->previousPageUrl() }}">‹ Precedenti</a>
                @endif
                @if ($tenants->nextPageUrl())
                    <a class="btn small secondary" href="{{ $tenants->nextPageUrl() }}">Successivi ›</a>
                @endif
            </div>
        @endif
    @endif
</div>

@endsection
