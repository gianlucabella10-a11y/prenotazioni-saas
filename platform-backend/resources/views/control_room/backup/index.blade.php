@extends('control_room.layout')
@section('title', 'Backup')
@section('content')

<div class="card">
    <h2>Backup del centro operativo</h2>
    <p class="muted" style="margin-top:0">
        Crea un archivio di database (sqlite) + storage (asset, manifest, APK) sotto <code>backups/</code>.
        Copialo poi fuori da questa macchina — un backup che vive solo qui non protegge da un guasto hardware.
    </p>
    <form method="post" action="{{ route('control.backup.store') }}"
          onsubmit="return confirm('Creare un nuovo backup ora?')">
        @csrf
        <button class="btn" type="submit">Backup ora</button>
    </form>
</div>

<div class="card">
    <h2>Backup esistenti</h2>
    @if (empty($backups))
        <div class="empty">Nessun backup ancora creato.</div>
    @else
        <table>
            <tr><th>File</th><th>Dimensione</th><th>Creato</th></tr>
            @foreach ($backups as $b)
                <tr>
                    <td><code>{{ $b['filename'] }}</code></td>
                    <td class="muted">{{ number_format($b['size_bytes'] / 1024, 1) }} KB</td>
                    <td class="muted">{{ \Illuminate\Support\Carbon::createFromTimestamp($b['created_at'])->format('d/m/Y H:i:s') }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

@endsection
