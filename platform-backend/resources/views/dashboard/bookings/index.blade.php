@extends('dashboard.layout')
@section('title', 'Prenotazioni')
@section('content')

@if ($pending->isNotEmpty())
    <div class="card" id="in-attesa" style="border-color: var(--warning)">
        <h2>⏳ Richieste in attesa di conferma</h2>
        <table>
            <tr><th>Quando</th><th>Cliente</th><th>Servizio</th><th>Operatore</th><th></th></tr>
            @foreach ($pending as $appointment)
                <tr>
                    <td>{{ $appointment->starts_at->setTimezone($timezone)->format('d/m H:i') }}</td>
                    <td>{{ $appointment->customer->fullName() }}</td>
                    <td>{{ $appointment->items->pluck('service_name_snapshot')->implode(' + ') }}</td>
                    <td>{{ $appointment->items->first()?->staffMember?->display_name }}</td>
                    <td style="white-space:nowrap">
                        <form class="inline-form" method="post"
                              action="{{ route('dashboard.bookings.confirm', $appointment->uuid) }}">
                            @csrf <button class="btn small" type="submit">Conferma</button>
                        </form>
                        <form class="inline-form" method="post"
                              action="{{ route('dashboard.bookings.cancel', $appointment->uuid) }}"
                              onsubmit="return confirm('Rifiutare questa richiesta? Il cliente sarà avvisato.')">
                            @csrf <button class="btn small danger" type="submit">Rifiuta</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endif

<div class="card">
    <form class="day-nav" method="get" action="{{ route('dashboard.bookings.index') }}">
        @php
            $prev = \Illuminate\Support\Carbon::parse($day)->subDay()->format('Y-m-d');
            $next = \Illuminate\Support\Carbon::parse($day)->addDay()->format('Y-m-d');
        @endphp
        <a class="btn small secondary" href="{{ route('dashboard.bookings.index', ['date' => $prev, 'staff' => $staffFilter]) }}">‹</a>
        <input type="date" name="date" value="{{ $day }}" onchange="this.form.submit()" style="max-width:170px">
        <a class="btn small secondary" href="{{ route('dashboard.bookings.index', ['date' => $next, 'staff' => $staffFilter]) }}">›</a>

        @if ($isOwner && $staffList->isNotEmpty())
            <select name="staff" onchange="this.form.submit()" style="max-width:220px">
                <option value="">Tutti gli operatori</option>
                @foreach ($staffList as $member)
                    <option value="{{ $member->uuid }}" @selected($staffFilter === $member->uuid)>
                        {{ $member->display_name }}
                    </option>
                @endforeach
            </select>
        @endif
    </form>
</div>

<div class="card">
    <h2>Agenda del {{ \Illuminate\Support\Carbon::parse($day)->format('d/m/Y') }}</h2>
    @if ($appointments->isEmpty())
        <div class="empty">Nessuna prenotazione in questo giorno.</div>
    @else
        <table>
            <tr><th>Ora</th><th>Cliente</th><th>Servizio</th><th>Operatore</th><th>Stato</th><th></th></tr>
            @foreach ($appointments as $appointment)
                @php $status = $appointment->status->value; @endphp
                <tr>
                    <td><strong>{{ $appointment->starts_at->setTimezone($timezone)->format('H:i') }}</strong></td>
                    <td>{{ $appointment->customer->fullName() }}
                        @if ($appointment->customer->no_show_count > 0)
                            <span class="badge danger" title="No-show passati">{{ $appointment->customer->no_show_count }} NS</span>
                        @endif
                    </td>
                    <td>{{ $appointment->items->pluck('service_name_snapshot')->implode(' + ') }}</td>
                    <td>{{ $appointment->items->first()?->staffMember?->display_name }}</td>
                    <td>
                        @switch($status)
                            @case('confirmed') <span class="badge ok">Confermato</span> @break
                            @case('requested') <span class="badge warn">In attesa</span> @break
                            @case('completed') <span class="badge ok">Completato</span> @break
                            @case('no_show') <span class="badge danger">No-show</span> @break
                            @default <span class="badge off">Annullato</span>
                        @endswitch
                    </td>
                    <td style="white-space:nowrap">
                        @if ($status === 'requested')
                            <form class="inline-form" method="post" action="{{ route('dashboard.bookings.confirm', $appointment->uuid) }}">
                                @csrf <button class="btn small" type="submit">Conferma</button>
                            </form>
                        @endif
                        @if ($status === 'confirmed')
                            <form class="inline-form" method="post" action="{{ route('dashboard.bookings.complete', $appointment->uuid) }}">
                                @csrf <button class="btn small" type="submit">Completa</button>
                            </form>
                            @if ($appointment->starts_at->isPast())
                                <form class="inline-form" method="post" action="{{ route('dashboard.bookings.noshow', $appointment->uuid) }}"
                                      onsubmit="return confirm('Registrare il no-show?')">
                                    @csrf <button class="btn small secondary" type="submit">No-show</button>
                                </form>
                            @endif
                        @endif
                        @if (in_array($status, ['confirmed', 'requested'], true))
                            <form class="inline-form" method="post" action="{{ route('dashboard.bookings.cancel', $appointment->uuid) }}"
                                  onsubmit="return confirm('Annullare? Il cliente sarà avvisato.')">
                                @csrf <button class="btn small danger" type="submit">Annulla</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

@endsection
