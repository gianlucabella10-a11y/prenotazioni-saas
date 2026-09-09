<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Control Room — Attiva MFA</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <style>:root { --primary: #0B1220; --secondary: #6366F1; }</style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <h1>Proteggi la Control Room</h1>
        <p class="sub">La verifica in due passaggi è obbligatoria per l'accesso proprietario.</p>

        @if ($errors->any())
            <div class="errors"><ul>
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul></div>
        @endif

        <p><strong>1.</strong> Aggiungi questo codice alla tua app authenticator
            (Google Authenticator, 1Password, Authy…):</p>
        <code class="copy">{{ $secret }}</code>
        <p class="muted" style="font-size:13px">
            Da smartphone puoi <a href="{{ $otpauthUri }}">aprire direttamente l'app</a>.
        </p>

        <p class="mt"><strong>2.</strong> Inserisci il codice a 6 cifre generato:</p>
        <form method="post" action="{{ route('control.mfa.confirm') }}">
            @csrf
            <input type="text" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}"
                   required autocomplete="one-time-code">
            <button class="btn mt" type="submit" style="width:100%">Attiva e accedi</button>
        </form>
    </div>
</div>
</body>
</html>
