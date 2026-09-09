<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Control Room — Accesso</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <style>:root { --primary: #0B1220; --secondary: #6366F1; }</style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <h1>Control Room</h1>
        <p class="sub">Accesso riservato al proprietario della piattaforma.</p>

        @if ($errors->any())
            <div class="errors"><ul>
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul></div>
        @endif

        <form method="post" action="{{ route('control.login.post') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" required autofocus value="{{ old('email') }}">

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>

            <button class="btn mt" type="submit" style="width:100%">Accedi</button>
        </form>
    </div>
</div>
</body>
</html>
