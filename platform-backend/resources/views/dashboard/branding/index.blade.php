@extends('dashboard.layout')
@section('title', 'Personalizzazione App')
@section('content')

<div class="card" style="margin-bottom:16px">
    <strong>Personalizzazione</strong>
    <p class="muted" style="margin:6px 0 10px">
        Ogni modifica si applica <strong>senza aggiornare l'app</strong>: i clienti la vedono
        alla prossima apertura. Solo logo/icone native entrano con una nuova build.
    </p>
    <nav style="display:flex;flex-wrap:wrap;gap:8px;font-size:14px">
        <a href="#cat-brand">Brand</a> ·
        <a href="#cat-tema">Tema</a> ·
        <a href="{{ route('dashboard.availability.index') }}">Booking ↗</a> ·
        <a href="#cat-cliente">Cliente</a> ·
        <a href="#cat-notifiche">Notifiche</a> ·
        <a href="#cat-contatti">Contatti</a> ·
        <a href="#cat-assets">Assets</a>
    </nav>
    <details style="margin-top:10px">
        <summary style="cursor:pointer;font-size:14px">Cosa si aggiorna subito e cosa richiede una nuova build</summary>
        <div class="grid cols-2" style="margin-top:10px">
            <div>
                <strong style="color:#15803D">✔ Subito (senza build)</strong>
                <ul style="margin:6px 0 0;padding-left:18px;font-size:13px">
                    @foreach(($buildMatrix['runtime'] ?? []) as $label)
                        <li>{{ $label }}</li>
                    @endforeach
                </ul>
            </div>
            <div>
                <strong style="color:#B45309">⟳ Richiede una nuova build</strong>
                <ul style="margin:6px 0 0;padding-left:18px;font-size:13px">
                    @foreach(($buildMatrix['build'] ?? []) as $label)
                        <li>{{ $label }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </details>
</div>

<div class="grid cols-2">
    <div class="card">
        <h2 id="cat-brand">Brand — Identità dell'app</h2>
        <p class="muted" style="margin-top:0">
            Nome, slogan e colori. Le modifiche arrivano sull'app alla prossima apertura.
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
                    <label for="secondary_color">Colore secondario *</label>
                    <input id="secondary_color" type="color" name="secondary_color"
                           value="{{ old('secondary_color', $brand->secondary_color) }}">
                </div>
            </div>

            <h2 class="mt" id="cat-tema">Tema — aspetto premium</h2>
            <p class="muted" style="margin-top:0">
                Modalità chiaro/scuro, accenti e stile. Cambiano l'aspetto dell'app senza ricompilare;
                il tema scuro viene generato automaticamente dai tuoi colori con contrasto garantito.
            </p>

            @php($theme = $brand->theme ?? [])
            @php($mode = old('mode', data_get($theme, 'mode', 'light')))

            <label for="mode">Modalità tema</label>
            <select id="mode" name="mode">
                <option value="light" @selected($mode === 'light')>Chiaro</option>
                <option value="dark" @selected($mode === 'dark')>Scuro</option>
                <option value="system" @selected($mode === 'system')>Automatico (segue il sistema)</option>
            </select>

            <div class="row">
                <div>
                    <label for="accent_color">Colore di richiamo (accent)</label>
                    <input id="accent_color" type="color" name="accent_color"
                           value="{{ old('accent_color', data_get($theme, 'colors.accent', $brand->secondary_color)) }}">
                </div>
                <div>
                    <label for="background_color">Colore sfondo</label>
                    <input id="background_color" type="color" name="background_color"
                           value="{{ old('background_color', data_get($theme, 'colors.background', '#F9FAFB')) }}">
                </div>
            </div>

            <div class="row">
                <div>
                    <label for="success_color">Colore conferma (success)</label>
                    <input id="success_color" type="color" name="success_color"
                           value="{{ old('success_color', data_get($theme, 'colors.success', '#15803D')) }}">
                </div>
                <div>
                    <label for="warning_color">Colore avviso (warning)</label>
                    <input id="warning_color" type="color" name="warning_color"
                           value="{{ old('warning_color', data_get($theme, 'colors.warning', '#B45309')) }}">
                </div>
            </div>

            <div class="row">
                <div>
                    @php($isFlat = (int) data_get($theme, 'radius.medium', 12) <= 6)
                    <label for="style">Stile forme</label>
                    <select id="style" name="style">
                        <option value="rounded" @selected(! $isFlat)>Arrotondato</option>
                        <option value="flat" @selected($isFlat)>Squadrato / minimal</option>
                    </select>
                </div>
                <div>
                    <label for="shadow_level">Livello ombra (0–4)</label>
                    <input id="shadow_level" type="number" name="shadow_level" min="0" max="4" step="1"
                           value="{{ old('shadow_level', (int) data_get($theme, 'elevation.level', 1)) }}">
                </div>
            </div>

            @php($density = old('density', data_get($theme, 'density', 'standard')))
            <label for="density">Densità dell'app (spaziature)</label>
            <select id="density" name="density">
                <option value="comfortable" @selected($density === 'comfortable')>Comoda (più spazio)</option>
                <option value="standard" @selected($density === 'standard')>Standard</option>
                <option value="compact" @selected($density === 'compact')>Compatta (più contenuti)</option>
            </select>

            <h2 class="mt" id="cat-cliente">Cliente — contenuti dell'app</h2>
            <p class="muted" style="margin-top:0">
                I testi che il cliente legge nell'app. Vuoto = testo predefinito.
            </p>
            @php($content = $brand->content ?? [])

            <label for="welcome_message">Messaggio di benvenuto (home)</label>
            <input id="welcome_message" type="text" name="welcome_message" maxlength="120"
                   value="{{ old('welcome_message', data_get($content, 'welcome_message')) }}"
                   placeholder="Benvenuto da...">

            <div class="row">
                <div>
                    <label for="home_title">Titolo sezione home</label>
                    <input id="home_title" type="text" name="home_title" maxlength="60"
                           value="{{ old('home_title', data_get($content, 'home_title')) }}"
                           placeholder="Il tuo prossimo appuntamento">
                </div>
                <div>
                    <label for="primary_cta_label">Testo pulsante prenota</label>
                    <input id="primary_cta_label" type="text" name="primary_cta_label" maxlength="30"
                           value="{{ old('primary_cta_label', data_get($content, 'primary_cta_label')) }}"
                           placeholder="Prenota ora">
                </div>
            </div>

            <label for="home_subtitle">Descrizione breve (home)</label>
            <input id="home_subtitle" type="text" name="home_subtitle" maxlength="160"
                   value="{{ old('home_subtitle', data_get($content, 'home_subtitle')) }}"
                   placeholder="Prenota il tuo appuntamento in pochi secondi">

            <label for="empty_appointments">Messaggio quando non ci sono appuntamenti</label>
            <input id="empty_appointments" type="text" name="empty_appointments" maxlength="120"
                   value="{{ old('empty_appointments', data_get($content, 'empty_appointments')) }}"
                   placeholder="Nessun appuntamento in programma.">

            <label for="hero_image_url">Immagine hero (URL https, facoltativa)</label>
            <input id="hero_image_url" type="url" name="hero_image_url" maxlength="255"
                   value="{{ old('hero_image_url', data_get($content, 'hero_image_url')) }}"
                   placeholder="https://.../hero.jpg">

            <h2 class="mt" id="cat-notifiche">Notifiche</h2>
            <p class="muted" style="margin-top:0">
                Aspetto delle notifiche push. Il colore è quello di accento sull'icona.
            </p>
            @php($notif = $brand->notification ?? [])
            <div class="row">
                <div>
                    <label for="notification_color">Colore notifica (vuoto = colore principale)</label>
                    <input id="notification_color" type="color" name="notification_color"
                           value="{{ old('notification_color', data_get($notif, 'color', $brand->primary_color)) }}">
                </div>
                <div>
                    @php($priority = old('notification_priority', data_get($notif, 'priority', 'high')))
                    <label for="notification_priority">Priorità</label>
                    <select id="notification_priority" name="notification_priority">
                        <option value="high" @selected($priority === 'high')>Alta (in evidenza)</option>
                        <option value="normal" @selected($priority === 'normal')>Normale (silenziosa)</option>
                    </select>
                </div>
            </div>

            <h2 class="mt">Link legali</h2>

            <label for="privacy_policy_url">Privacy policy (URL https)</label>
            <input id="privacy_policy_url" type="url" name="privacy_policy_url" maxlength="255"
                   value="{{ old('privacy_policy_url', $brand->privacy_policy_url) }}"
                   placeholder="https://...">

            <label for="terms_url">Termini e condizioni (URL https)</label>
            <input id="terms_url" type="url" name="terms_url" maxlength="255"
                   value="{{ old('terms_url', $brand->terms_url) }}" placeholder="https://...">

            <label for="cookie_url">Cookie policy (URL https)</label>
            <input id="cookie_url" type="url" name="cookie_url" maxlength="255"
                   value="{{ old('cookie_url', $brand->cookie_url) }}" placeholder="https://...">

            <label for="support_url">Assistenza (URL https)</label>
            <input id="support_url" type="url" name="support_url" maxlength="255"
                   value="{{ old('support_url', $brand->support_url) }}" placeholder="https://...">

            <label for="vat_number">Partita IVA</label>
            <input id="vat_number" type="text" name="vat_number" maxlength="32"
                   value="{{ old('vat_number', $brand->vat_number) }}" placeholder="IT01234567890">

            <h2 class="mt" id="cat-contatti">Contatti e social (mostrati nell'app)</h2>
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

            <label for="tiktok_url">TikTok (URL https)</label>
            <input id="tiktok_url" type="url" name="tiktok_url" maxlength="255"
                   value="{{ old('tiktok_url', $brand->tiktok_url) }}" placeholder="https://tiktok.com/@...">

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

            <div class="row">
                <div>
                    <label for="latitude">Latitudine (GPS, facoltativa)</label>
                    <input id="latitude" type="number" step="0.0000001" min="-90" max="90" name="latitude"
                           value="{{ old('latitude', $location->latitude) }}" placeholder="45.4642">
                </div>
                <div>
                    <label for="longitude">Longitudine (GPS, facoltativa)</label>
                    <input id="longitude" type="number" step="0.0000001" min="-180" max="180" name="longitude"
                           value="{{ old('longitude', $location->longitude) }}" placeholder="9.1900">
                </div>
            </div>
            <p class="muted" style="font-size:12px;margin-top:4px">
                Con le coordinate, il pulsante Mappa nell'app apre il pin esatto della sede.
            </p>

            <button class="btn mt" type="submit">Salva contatti</button>
        </form>

        <div class="mt muted" style="font-size:13px">
            Versione configurazione app: <strong>v{{ $brand->config_version }}</strong>
        </div>
    </div>

    <div class="card">
        <h2 id="cat-assets">Assets — logo e icone</h2>
        <p class="muted" style="margin-top:0">
            Carica il logo (PNG/JPG/WebP, minimo 256×256, consigliato 1024×1024). Da qui
            generiamo automaticamente icone, splash e favicon. Il logo nell'app si aggiorna
            subito; le icone native entrano con la prossima build.
        </p>
        <form method="post" action="{{ route('dashboard.branding.logo') }}" enctype="multipart/form-data">
            @csrf
            <label for="logo">Logo (file immagine)</label>
            <input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp" required>
            <button class="btn mt" type="submit">Carica logo e rigenera asset</button>
        </form>
    </div>
</div>

@endsection
