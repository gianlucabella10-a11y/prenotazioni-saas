@extends('dashboard.layout')
@section('title', 'Operatori')
@section('content')

<div class="card">
    <a class="btn" href="{{ route('dashboard.staff.create') }}">+ Nuovo operatore</a>
</div>

<div class="card">
    @if ($staff->isEmpty())
        <div class="empty">Nessun operatore.</div>
    @else
        <table>
            <tr><th>Nome</th><th>Ruolo</th><th>Servizi</th><th>Stato</th><th></th></tr>
            @foreach ($staff as $member)
                <tr>
                    <td><strong>{{ $member->display_name }}</strong></td>
                    <td class="muted">{{ $member->role_label ?? '—' }}</td>
                    <td class="muted">{{ $member->services->pluck('name')->implode(', ') ?: '—' }}</td>
                    <td>
                        @if ($member->is_bookable)
                            <span class="badge ok">Prenotabile</span>
                        @else
                            <span class="badge off">Non prenotabile</span>
                        @endif
                    </td>
                    <td style="white-space:nowrap">
                        <a class="btn small secondary" href="{{ route('dashboard.staff.edit', $member->uuid) }}">Modifica</a>
                        <form class="inline-form" method="post" action="{{ route('dashboard.staff.destroy', $member->uuid) }}"
                              onsubmit="return confirm('Disattivare «{{ $member->display_name }}»? L\'agenda passata resta visibile.')">
                            @csrf @method('DELETE')
                            <button class="btn small danger" type="submit">Disattiva</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

@endsection
