<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Control Room — Verifica</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <style>:root { --primary: #0B1220; --secondary: #6366F1; }</style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <h1>Verifica in due passaggi</h1>
        <p class="sub">Inserisci il codice a 6 cifre della tua app authenticator.</p>

        @if ($errors->any())
            <div class="errors"><ul>
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul></div>
        @endif

        <form method="post" action="{{ route('control.mfa.verify') }}">
            @csrf
            <input type="text" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}"
                   required autofocus autocomplete="one-time-code">
            <button class="btn mt" type="submit" style="width:100%">Verifica</button>
        </form>
    </div>
</div>
</body>
</html>
