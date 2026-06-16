@extends('control_room.layout')
@section('title', 'Nuovo cliente')
@section('content')

<div class="card" style="max-width:680px">
    <form method="post" action="{{ route('control.tenants.store') }}">
        @csrf

        <label for="display_name">Nome attività *</label>
        <input id="display_name" name="display_name" required minlength="2" maxlength="30"
               value="{{ old('display_name') }}">

        <label for="sector">Categoria *</label>
        <select id="sector" name="sector" required>
            @foreach (['barber' => 'Barbiere', 'hair' => 'Parrucchiere', 'beauty' => 'Estetista', 'dental' => 'Dentista', 'medical' => 'Medico', 'physio' => 'Fisioterapista', 'consultant' => 'Consulente', 'other' => 'Altro'] as $val => $lbl)
                <option value="{{ $val }}" @selected(old('sector') === $val)>{{ $lbl }}</option>
            @endforeach
        </select>

        <label for="admin_email">Email del titolare *</label>
        <input id="admin_email" type="email" name="admin_email" required value="{{ old('admin_email') }}">

        <div class="row">
            <div>
                <label for="phone">Telefono</label>
                <input id="phone" name="phone" maxlength="32" value="{{ old('phone') }}">
            </div>
            <div>
                <label for="primary_color">Colore principale</label>
                <input id="primary_color" type="color" name="primary_color" value="{{ old('primary_color', '#1F2937') }}">
            </div>
        </div>

        <label for="address">Indirizzo</label>
        <input id="address" name="address" maxlength="255" value="{{ old('address') }}">

        <label for="plan_code">Piano *</label>
        <select id="plan_code" name="plan_code" required>
            @foreach ($plans as $plan)
                <option value="{{ $plan->code }}" @selected(old('plan_code') === $plan->code)>
                    {{ $plan->name }} — {{ number_format($plan->price_monthly_cents / 100, 0) }}€/mese
                </option>
            @endforeach
        </select>

        <label for="template_code">Template app</label>
        <select id="template_code" name="template_code">
            @foreach ($templates as $code => $tpl)
                <option value="{{ $code }}" @selected(old('template_code', 'default') === $code)>{{ $tpl['label'] }}</option>
            @endforeach
        </select>

        <div class="checkbox mt">
            <input id="health_data" type="checkbox" name="health_data" value="1" @checked(old('health_data'))>
            <label for="health_data" style="margin:0">Attiva modulo dati sanitari (obbligatorio per dentista / medico / fisioterapista)</label>
        </div>

        <div class="mt">
            <button class="btn" type="submit">Crea cliente</button>
            <a class="btn secondary" href="{{ route('control.tenants.index') }}">Annulla</a>
        </div>

        <p class="muted mt" style="font-size:13px">
            Alla creazione vengono generati automaticamente: tenant, titolare (con invito),
            brand iniziale, sede, orari e catalogo del settore. Il logo si carica dalla scheda cliente.
        </p>
    </form>
</div>

@endsection
