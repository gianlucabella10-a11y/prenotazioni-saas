<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Benvenuto — imposta la password</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <h1>Benvenuto!</h1>
        <p class="sub">Imposta la password per accedere alla gestione della tua attività.</p>

        @if ($errors->any())
            <div class="errors"><ul>
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul></div>
        @endif

        <form method="post" action="{{ route('dashboard.invite.accept') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required>

            <label for="token">Codice invito</label>
            <input id="token" type="text" name="token" value="{{ old('token', $token) }}" required>

            <label for="password">Nuova password (minimo 10 caratteri)</label>
            <input id="password" type="password" name="password" minlength="10" required>

            <label for="password_confirmation">Conferma password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" minlength="10" required>

            <button class="btn mt" type="submit" style="width:100%">Imposta password</button>
        </form>
    </div>
</div>
</body>
</html>
