{{-- Control Room layout: shell interno proprietario. Riusa dashboard.css ma
     con palette piattaforma DISTINTA (sicurezza operativa: l'operatore sa
     sempre di NON essere in un dashboard cliente). --}}
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Control Room — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <style>
        :root { --primary: #0B1220; --secondary: #6366F1; }
        .cr-tag { display:inline-block; background:var(--secondary); color:#fff; font-size:10px;
            font-weight:700; padding:2px 7px; border-radius:99px; letter-spacing:.05em; vertical-align:middle; }
        code { font-size: 12px; }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">Control Room <span class="cr-tag">INTERNO</span></div>
        <nav>
            <a href="{{ route('control.home') }}"
               class="{{ request()->routeIs('control.home') ? 'active' : '' }}">Cruscotto</a>
            <a href="{{ route('control.tenants.index') }}"
               class="{{ request()->routeIs('control.tenants.index') || request()->routeIs('control.tenants.show') ? 'active' : '' }}">Clienti</a>
            <a href="{{ route('control.tenants.create') }}"
               class="{{ request()->routeIs('control.tenants.create') ? 'active' : '' }}">+ Nuovo cliente</a>
            <a href="{{ route('control.apps.index') }}"
               class="{{ request()->routeIs('control.apps.index') || request()->routeIs('control.apps.show') ? 'active' : '' }}">App</a>
            <a href="{{ route('control.apps.fleet') }}"
               class="{{ request()->routeIs('control.apps.fleet') ? 'active' : '' }}">Flotta</a>
            <a href="{{ route('control.audit.index') }}"
               class="{{ request()->routeIs('control.audit.index') ? 'active' : '' }}">Audit</a>
            <a href="{{ route('control.backup.index') }}"
               class="{{ request()->routeIs('control.backup.*') ? 'active' : '' }}">Backup</a>
            <a href="{{ route('control.logs.index') }}"
               class="{{ request()->routeIs('control.logs.index') ? 'active' : '' }}">Log</a>
        </nav>
        <div class="foot">
            {{ auth('admin')->user()?->email }}<br>
            <span style="opacity:.8">Super Admin</span>
            <form method="post" action="{{ route('control.logout') }}" class="mt">
                @csrf
                <button class="btn small secondary" type="submit">Esci</button>
            </form>
        </div>
    </aside>

    <main class="main">
        <div class="topbar"><h1>@yield('title')</h1></div>

        @if (session('status'))
            <div class="flash ok">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="flash err">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="errors"><ul>
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul></div>
        @endif

        @yield('content')
    </main>
</div>
</body>
</html>
