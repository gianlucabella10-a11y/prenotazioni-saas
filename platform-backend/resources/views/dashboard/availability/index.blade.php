@extends('dashboard.layout')
@section('title', 'Disponibilità')
@section('content')

<div class="card">
    <h2>Regole di prenotazione</h2>
    <p class="muted" style="margin-top:0">
        Finestra, preavviso, durata slot e cancellazione. Valgono da subito, senza aggiornare l'app.
    </p>
    <form method="post" action="{{ route('dashboard.availability.policy') }}">
        @csrf @method('PUT')
        <div class="row">
            <div>
                <label for="booking_window_days">Finestra prenotabile (giorni)</label>
                <input id="booking_window_days" type="number" name="booking_window_days" min="1" max="365"
                       value="{{ old('booking_window_days', $location->booking_window_days) }}">
            </div>
            <div>
                <label for="slot_granularity_minutes">Durata slot (minuti)</label>
                <input id="slot_granularity_minutes" type="number" name="slot_granularity_minutes" min="5" max="120" step="5"
                       value="{{ old('slot_granularity_minutes', $location->slot_granularity_minutes) }}">
            </div>
        </div>
        <div class="row">
            <div>
                <label for="min_notice_minutes">Preavviso minimo (minuti)</label>
                <input id="min_notice_minutes" type="number" name="min_notice_minutes" min="0" max="10080"
                       value="{{ old('min_notice_minutes', $location->min_notice_minutes) }}">
            </div>
            <div>
                <label for="cancellation_cutoff_minutes">Cancellazione entro (minuti prima)</label>
                <input id="cancellation_cutoff_minutes" type="number" name="cancellation_cutoff_minutes" min="0" max="10080"
                       value="{{ old('cancellation_cutoff_minutes', $location->cancellation_cutoff_minutes) }}">
            </div>
        </div>
        <label for="max_active_bookings_per_customer">Max prenotazioni attive per cliente (0 = illimitate)</label>
        <input id="max_active_bookings_per_customer" type="number" name="max_active_bookings_per_customer" min="0" max="50"
               value="{{ old('max_active_bookings_per_customer', data_get($location->settings, 'max_active_bookings_per_customer', 0)) }}">
        <button class="btn mt" type="submit">Salva regole</button>
    </form>
</div>

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
