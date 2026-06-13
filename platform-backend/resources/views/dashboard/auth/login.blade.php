<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accesso — Gestione</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <h1>Area gestione</h1>
        <p class="sub">Accedi con le credenziali della tua attività.</p>

        @if (session('status'))<div class="flash ok">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="errors"><ul>
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul></div>
        @endif

        <form method="post" action="{{ route('dashboard.login.post') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>

            <button class="btn mt" type="submit" style="width:100%">Accedi</button>
        </form>

        <p class="muted mt" style="font-size:13px">
            Hai ricevuto un invito? <a href="{{ route('dashboard.invite') }}">Imposta la password</a>
        </p>
    </div>
</div>
</body>
</html>
