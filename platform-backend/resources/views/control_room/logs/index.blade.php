@extends('control_room.layout')
@section('title', 'Log applicativi')
@section('content')

<div class="card">
    <h2>storage/logs/laravel.log</h2>
    @if (! $available)
        <div class="empty">Nessun file di log trovato.</div>
    @else
        <p class="muted" style="margin-top:0">
            Ultime {{ count($lines) }} righe · dimensione file {{ number_format($sizeBytes / 1024, 1) }} KB
        </p>
        <pre style="background:#0B1220; color:#D1D5DB; padding:14px; border-radius:8px; max-height:70vh; overflow:auto; font-size:12px; line-height:1.5">{{ implode("\n", $lines) }}</pre>
    @endif
</div>

@endsection
