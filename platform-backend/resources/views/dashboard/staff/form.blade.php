@extends('dashboard.layout')
@section('title', $staff ? 'Modifica operatore' : 'Nuovo operatore')
@section('content')

<div class="card" style="max-width:760px">
    <form method="post"
          action="{{ $staff ? route('dashboard.staff.update', $staff->uuid) : route('dashboard.staff.store') }}">
        @csrf
        @if ($staff) @method('PUT') @endif

        <div class="row">
            <div>
                <label for="display_name">Nome *</label>
                <input id="display_name" type="text" name="display_name" required maxlength="255"
                       value="{{ old('display_name', $staff->display_name ?? '') }}">
            </div>
            <div>
                <label for="role_label">Ruolo (mostrato nell'app)</label>
                <input id="role_label" type="text" name="role_label" maxlength="255"
                       value="{{ old('role_label', $staff->role_label ?? '') }}"
                       placeholder="es. Senior Barber">
            </div>
        </div>

        <div class="checkbox">
            <input id="is_bookable" type="checkbox" name="is_bookable" value="1"
                   @checked(old('is_bookable', $staff->is_bookable ?? true))>
            <label for="is_bookable" style="margin:0">Prenotabile dai clienti</label>
        </div>

        <label class="mt">Servizi che esegue</label>
        @php $selectedServices = old('service_ids', $staff?->services->pluck('id')->all() ?? []); @endphp
        @foreach ($services as $service)
            <div class="checkbox" style="margin-top:4px">
                <input id="svc-{{ $service->id }}" type="checkbox" name="service_ids[]"
                       value="{{ $service->id }}"
                       @checked(in_array($service->id, $selectedServices))>
                <label for="svc-{{ $service->id }}" style="margin:0; font-weight:400">{{ $service->name }}</label>
            </div>
        @endforeach

        <h2 class="mt">Orari settimanali</h2>
        <p class="muted" style="margin-top:0">
            Questi orari determinano gli slot prenotabili dai clienti.
            Lascia vuoto per il giorno di riposo.
        </p>
        <table class="schedule-grid">
            <tr><th>Giorno</th><th colspan="2">Mattina</th><th colspan="2">Pomeriggio</th></tr>
            @foreach (range(0, 6) as $weekday)
                <tr>
                    <td><strong>{{ \App\Modules\Dashboard\Http\Controllers\StaffController::weekdayName($weekday) }}</strong></td>
                    <td><input type="time" name="schedule[{{ $weekday }}][morning][start]"
                               value="{{ old("schedule.$weekday.morning.start", $schedule[$weekday]['morning']['start']) }}"></td>
                    <td><input type="time" name="schedule[{{ $weekday }}][morning][end]"
                               value="{{ old("schedule.$weekday.morning.end", $schedule[$weekday]['morning']['end']) }}"></td>
                    <td><input type="time" name="schedule[{{ $weekday }}][afternoon][start]"
                               value="{{ old("schedule.$weekday.afternoon.start", $schedule[$weekday]['afternoon']['start']) }}"></td>
                    <td><input type="time" name="schedule[{{ $weekday }}][afternoon][end]"
                               value="{{ old("schedule.$weekday.afternoon.end", $schedule[$weekday]['afternoon']['end']) }}"></td>
                </tr>
            @endforeach
        </table>

        <div class="mt">
            <button class="btn" type="submit">{{ $staff ? 'Salva modifiche' : 'Crea operatore' }}</button>
            <a class="btn secondary" href="{{ route('dashboard.staff.index') }}">Annulla</a>
        </div>
    </form>
</div>

@endsection
