@extends('dashboard.layout')
@section('title', 'Disponibilità')
@section('content')

<div class="card">
    <h2>Chiusure e ferie</h2>
    <p class="muted" style="margin-top:0">
        Blocca giorni o fasce orarie: gli slot spariscono subito dall'app.
    </p>

    <form method="post" action="{{ route('dashboard.availability.exceptions.store') }}">
        @csrf
        <div class="row">
            <div>
                <label>Chi</label>
                <select name="scope" onchange="document.getElementById('staff-select').style.display = this.value === 'staff' ? '' : 'none'">
                    <option value="location">Tutta la sede</option>
                    <option value="staff">Singolo operatore</option>
                </select>
            </div>
            <div id="staff-select" style="display:none">
                <label>Operatore</label>
                <select name="staff_uuid">
                    @foreach ($staff as $member)
                        <option value="{{ $member->uuid }}">{{ $member->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Dal *</label>
                <input type="date" name="date_start" required value="{{ old('date_start') }}">
            </div>
            <div>
                <label>Al *</label>
                <input type="date" name="date_end" required value="{{ old('date_end') }}">
            </div>
        </div>
        <div class="row">
            <div>
                <label>Dalle (vuoto = tutto il giorno)</label>
                <input type="time" name="time_start" value="{{ old('time_start') }}">
            </div>
            <div>
                <label>Alle</label>
                <input type="time" name="time_end" value="{{ old('time_end') }}">
            </div>
            <div>
                <label>Motivo (interno)</label>
                <input type="text" name="reason" maxlength="255" value="{{ old('reason') }}" placeholder="es. Ferie estive">
            </div>
            <div>
                <button class="btn" type="submit" style="width:100%">Aggiungi chiusura</button>
            </div>
        </div>
    </form>

    @if ($exceptions->isEmpty())
        <div class="empty">Nessuna chiusura programmata.</div>
    @else
        <table class="mt">
            <tr><th>Periodo</th><th>Orario</th><th>Chi</th><th>Motivo</th><th></th></tr>
            @foreach ($exceptions as $exception)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($exception->date_start)->format('d/m/Y') }}
                        → {{ \Illuminate\Support\Carbon::parse($exception->date_end)->format('d/m/Y') }}</td>
                    <td>
                        @if ($exception->time_start)
                            {{ substr($exception->time_start, 0, 5) }}–{{ substr($exception->time_end, 0, 5) }}
                        @else
                            Tutto il giorno
                        @endif
                    </td>
                    <td>{{ $exception->staffMember?->display_name ?? 'Tutta la sede' }}</td>
                    <td class="muted">{{ $exception->reason ?? '—' }}</td>
                    <td>
                        <form class="inline-form" method="post"
                              action="{{ route('dashboard.availability.exceptions.destroy', $exception->uuid) }}"
                              onsubmit="return confirm('Rimuovere questa chiusura?')">
                            @csrf @method('DELETE')
                            <button class="btn small danger" type="submit">Rimuovi</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

<div class="card">
    <h2>Orari della sede</h2>
    <p class="muted" style="margin-top:0">
        Orari di apertura mostrati ai clienti. Gli slot prenotabili seguono
        gli orari dei singoli operatori (scheda Operatori).
    </p>
    <form method="post" action="{{ route('dashboard.availability.hours') }}">
        @csrf @method('PUT')
        <table class="schedule-grid">
            <tr><th>Giorno</th><th colspan="2">Mattina</th><th colspan="2">Pomeriggio</th></tr>
            @foreach (range(0, 6) as $weekday)
                <tr>
                    <td><strong>{{ \App\Modules\Dashboard\Http\Controllers\StaffController::weekdayName($weekday) }}</strong></td>
                    <td><input type="time" name="schedule[{{ $weekday }}][morning][start]"
                               value="{{ $locationSchedule[$weekday]['morning']['start'] }}"></td>
                    <td><input type="time" name="schedule[{{ $weekday }}][morning][end]"
                               value="{{ $locationSchedule[$weekday]['morning']['end'] }}"></td>
                    <td><input type="time" name="schedule[{{ $weekday }}][afternoon][start]"
                               value="{{ $locationSchedule[$weekday]['afternoon']['start'] }}"></td>
                    <td><input type="time" name="schedule[{{ $weekday }}][afternoon][end]"
                               value="{{ $locationSchedule[$weekday]['afternoon']['end'] }}"></td>
                </tr>
            @endforeach
        </table>
        <button class="btn mt" type="submit">Salva orari sede</button>
    </form>
</div>

@endsection
