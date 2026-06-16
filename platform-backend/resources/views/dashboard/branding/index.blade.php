@extends('dashboard.layout')
@section('title', 'Personalizzazione App')
@section('content')

<div class="grid cols-2">
    <div class="card">
        <h2>Identità dell'app</h2>
        <p class="muted" style="margin-top:0">
            Le modifiche arrivano sull'app dei tuoi clienti alla prossima apertura.
        </p>
        <form method="post" action="{{ route('dashboard.branding.brand') }}">
            @csrf @method('PUT')

            <label for="app_name">Nome attività / app *</label>
            <input id="app_name" type="text" name="app_name" required minlength="2" maxlength="30"
                   value="{{ old('app_name', $brand->app_name) }}">

            <label for="tagline">Slogan (facoltativo)</label>
            <input id="tagline" type="text" name="tagline" maxlength="80"
                   value="{{ old('tagline', $brand->tagline) }}">

            <div class="row">
                <div>
                    <label for="primary_color">Colore principale *</label>
                    <input id="primary_color" type="color" name="primary_color"
                           value="{{ old('primary_color', $brand->primary_color) }}">
                </div>
                <div>
                    <label for="secondary_color">Colore accento *</label>
                    <input id="secondary_color" type="color" name="secondary_color"
                           value="{{ old('secondary_color', $brand->secondary_color) }}">
                </div>
            </div>

            <label for="privacy_policy_url">Privacy policy (URL https)</label>
            <input id="privacy_policy_url" type="url" name="privacy_policy_url" maxlength="255"
                   value="{{ old('privacy_policy_url', $brand->privacy_policy_url) }}"
                   placeholder="https://...">

            <label for="terms_url">Termini e condizioni (URL https)</label>
            <input id="terms_url" type="url" name="terms_url" maxlength="255"
                   value="{{ old('terms_url', $brand->terms_url) }}" placeholder="https://...">

            <label for="support_url">Assistenza (URL https)</label>
            <input id="support_url" type="url" name="support_url" maxlength="255"
                   value="{{ old('support_url', $brand->support_url) }}" placeholder="https://...">

            <h2 class="mt">Contatti e social (mostrati nell'app)</h2>
            <p class="muted" style="margin-top:0">Lascia vuoto un campo per nasconderlo nell'app.</p>

            <label for="contact_email">Email pubblica</label>
            <input id="contact_email" type="email" name="contact_email" maxlength="255"
                   value="{{ old('contact_email', $brand->contact_email) }}" placeholder="info@...">

            <label for="website_url">Sito web (URL https)</label>
            <input id="website_url" type="url" name="website_url" maxlength="255"
                   value="{{ old('website_url', $brand->website_url) }}" placeholder="https://...">

            <div class="row">
                <div>
                    <label for="whatsapp_number">WhatsApp (numero con prefisso)</label>
                    <input id="whatsapp_number" type="text" name="whatsapp_number" maxlength="32"
                           value="{{ old('whatsapp_number', $brand->whatsapp_number) }}" placeholder="+39333...">
                </div>
                <div>
                    <label for="whatsapp_message">Messaggio WhatsApp precompilato</label>
                    <input id="whatsapp_message" type="text" name="whatsapp_message" maxlength="255"
                           value="{{ old('whatsapp_message', $brand->whatsapp_message) }}"
                           placeholder="Ciao, vorrei informazioni...">
                </div>
            </div>

            <label for="instagram_url">Instagram (URL https)</label>
            <input id="instagram_url" type="url" name="instagram_url" maxlength="255"
                   value="{{ old('instagram_url', $brand->instagram_url) }}" placeholder="https://instagram.com/...">

            <label for="facebook_url">Facebook (URL https)</label>
            <input id="facebook_url" type="url" name="facebook_url" maxlength="255"
                   value="{{ old('facebook_url', $brand->facebook_url) }}" placeholder="https://facebook.com/...">

            <label for="maps_url">Google Maps (URL https — vuoto = generato dall'indirizzo)</label>
            <input id="maps_url" type="url" name="maps_url" maxlength="255"
                   value="{{ old('maps_url', $brand->maps_url) }}" placeholder="https://maps.google.com/...">

            <button class="btn mt" type="submit">Salva personalizzazione</button>
        </form>
    </div>

    <div class="card">
        <h2>Contatti e sede</h2>
        <p class="muted" style="margin-top:0">Mostrati nell'app nella scheda informazioni.</p>
        <form method="post" action="{{ route('dashboard.branding.contacts') }}">
            @csrf @method('PUT')

            <label for="name">Nome sede *</label>
            <input id="name" type="text" name="name" required maxlength="255"
                   value="{{ old('name', $location->name) }}">

            <label for="address">Indirizzo</label>
            <input id="address" type="text" name="address" maxlength="255"
                   value="{{ old('address', $location->address) }}">

            <label for="phone">Telefono</label>
            <input id="phone" type="text" name="phone" maxlength="32"
                   value="{{ old('phone', $location->phone) }}">

            <button class="btn mt" type="submit">Salva contatti</button>
        </form>

        <div class="mt muted" style="font-size:13px">
            Versione configurazione app: <strong>v{{ $brand->config_version }}</strong>
        </div>
    </div>
</div>

@endsection
