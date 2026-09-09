@extends('control_room.layout')
@section('title', 'App')
@section('content')

<div class="card">
    @if ($projects->isEmpty())
        <div class="empty">Nessuna app. Le app nascono insieme al cliente: vai su «Clienti» → «+ Nuovo cliente».</div>
    @else
        <table>
            <tr>
                <th>Attività</th><th>Template</th><th>Bundle ID</th>
                <th>Stato build</th><th>Ultima generazione</th><th></th>
            </tr>
            @foreach ($projects as $project)
                @php $tenant = $tenants[$project->tenant_id] ?? null; @endphp
                <tr>
                    <td><strong>{{ $tenant?->display_name ?? '—' }}</strong></td>
                    <td class="muted">{{ $project->template_code }}</td>
                    <td><code>{{ $project->bundle_id }}</code></td>
                    <td>
                        @php
                            $m = [
                                'draft' => ['off', 'Bozza'],
                                'configured' => ['warn', 'Configurata'],
                                'ready' => ['warn', 'Pronta'],
                                'generated' => ['ok', 'Generata'],
                                'ready_to_build' => ['ok', 'Pronta build'],
                                'building' => ['warn', 'In build'],
                                'built' => ['ok', 'Compilata'],
                                'published' => ['ok', 'Pubblicata'],
                                'failed' => ['danger', 'Fallita'],
                            ];
                            [$c, $l] = $m[$project->build_status->value] ?? ['off', $project->build_status->value];
                        @endphp
                        <span class="badge {{ $c }}">{{ $l }}</span>
                    </td>
                    <td class="muted">{{ $project->last_generated_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td><a class="btn small secondary" href="{{ route('control.apps.show', $project->uuid) }}">Apri</a></td>
                </tr>
            @endforeach
        </table>

        @if ($projects->hasPages())
            <div class="mt" style="display:flex; gap:8px">
                @if ($projects->previousPageUrl())
                    <a class="btn small secondary" href="{{ $projects->previousPageUrl() }}">‹ Precedenti</a>
                @endif
                @if ($projects->nextPageUrl())
                    <a class="btn small secondary" href="{{ $projects->nextPageUrl() }}">Successivi ›</a>
                @endif
            </div>
        @endif
    @endif
</div>

@endsection
