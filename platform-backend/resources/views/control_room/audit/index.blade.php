@extends('control_room.layout')
@section('title', 'Audit log')
@section('content')

<div class="card">
    <form method="get" action="{{ route('control.audit.index') }}" class="row" style="align-items:end">
        <div>
            <label for="action">Azione</label>
            <input id="action" type="text" name="action" value="{{ $action }}" placeholder="es. tenant.suspended">
        </div>
        <div><button class="btn" type="submit">Filtra</button></div>
    </form>
</div>

<div class="card">
    @if ($entries->isEmpty())
        <div class="empty">Nessuna voce trovata.</div>
    @else
        <table>
            <tr>
                <th>Data/ora</th><th>Azione</th><th>Tenant</th><th>Attore</th><th>Oggetto</th><th>IP</th>
            </tr>
            @foreach ($entries as $entry)
                <tr>
                    <td class="muted">{{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('d/m/Y H:i:s') }}</td>
                    <td><code>{{ $entry->action }}</code></td>
                    <td class="muted">{{ $entry->tenant_id ?? '—' }}</td>
                    <td class="muted">{{ $entry->actor_user_id ?? '—' }}</td>
                    <td class="muted">{{ $entry->subject_type ? class_basename($entry->subject_type).' #'.$entry->subject_id : '—' }}</td>
                    <td class="muted">{{ $entry->ip ?? '—' }}</td>
                </tr>
            @endforeach
        </table>

        @if ($entries->hasPages())
            <div class="mt" style="display:flex; gap:8px">
                @if ($entries->previousPageUrl())
                    <a class="btn small secondary" href="{{ $entries->previousPageUrl() }}">‹ Precedenti</a>
                @endif
                @if ($entries->nextPageUrl())
                    <a class="btn small secondary" href="{{ $entries->nextPageUrl() }}">Successivi ›</a>
                @endif
            </div>
        @endif
    @endif
</div>

@endsection
