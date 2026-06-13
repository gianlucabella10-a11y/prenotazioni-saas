{{-- Layout dashboard: sidebar + contenuto. Il brand del tenant colora la
     shell via CSS variables (white label, docs/31 §1). --}}
@php
    $brand = \App\Modules\Branding\Infrastructure\Models\BrandProfile::query()->first();
    $isOwner = auth()->user()?->type === \App\Foundation\Enums\UserType::TenantAdmin;
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brand?->app_name ?? config('app.name') }} — Gestione</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <style>
        :root {
            --primary: {{ $brand?->primary_color ?? '#1F2937' }};
            --secondary: {{ $brand?->secondary_color ?? '#C8A24B' }};
        }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">{{ $brand?->app_name ?? 'Dashboard' }}</div>
        <nav>
            <a href="{{ route('dashboard.home') }}"
               class="{{ request()->routeIs('dashboard.home') ? 'active' : '' }}">Home</a>
            <a href="{{ route('dashboard.bookings.index') }}"
               class="{{ request()->routeIs('dashboard.bookings.*') ? 'active' : '' }}">Prenotazioni</a>
            @if ($isOwner)
                <a href="{{ route('dashboard.services.index') }}"
                   class="{{ request()->routeIs('dashboard.services.*') ? 'active' : '' }}">Servizi</a>
                <a href="{{ route('dashboard.staff.index') }}"
                   class="{{ request()->routeIs('dashboard.staff.*') ? 'active' : '' }}">Operatori</a>
                <a href="{{ route('dashboard.availability.index') }}"
                   class="{{ request()->routeIs('dashboard.availability.*') ? 'active' : '' }}">Disponibilità</a>
                <a href="{{ route('dashboard.branding.index') }}"
                   class="{{ request()->routeIs('dashboard.branding.*') ? 'active' : '' }}">Personalizzazione</a>
            @endif
        </nav>
        <div class="foot">
            {{ auth()->user()?->email }}<br>
            <span style="opacity:.8">{{ $isOwner ? 'Titolare' : 'Operatore' }}</span>
            <form method="post" action="{{ route('dashboard.logout') }}" class="mt">
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
