@extends('control_room.layout')
@section('title', $tenant->display_name . ' — App')
@section('content')

<div class="card">
    <h2 style="margin-top:0">Anteprima brand</h2>
    <div style="display:flex; gap:28px; align-items:center; flex-wrap:wrap">
        <div style="text-align:center">
            @if ($preview['logo_url'])
                <img src="{{ $preview['logo_url'] }}" alt="logo" style="width:96px;height:96px;object-fit:contain;border:1px solid #e5e7eb;border-radius:12px;background:#fff">
            @else
                <div class="muted" style="width:96px;height:96px;border:1px dashed #cbd5e1;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:12px">no logo</div>
            @endif
            <div class="muted" style="font-size:12px;margin-top:4px">Logo</div>
        </div>
        <div style="text-align:center">
            @if ($preview['icon_url'])
                <img src="{{ $preview['icon_url'] }}" alt="icona app" style="width:72px;height:72px;object-fit:cover;border-radius:18px;box-shadow:0 2px 8px rgba(0,0,0,.15)">
            @else
                <div style="width:72px;height:72px;border-radius:18px;background:{{ $preview['colors']['primary'] ?? '#1F2937' }}"></div>
            @endif
            <div class="muted" style="font-size:12px;margin-top:4px">Icona app</div>
        </div>
        <div>
            <div style="display:flex;gap:8px">
                <span title="primary" style="width:28px;height:28px;border-radius:6px;display:inline-block;background:{{ $preview['colors']['primary'] ?? '#ccc' }}"></span>
                <span title="secondary" style="width:28px;height:28px;border-radius:6px;display:inline-block;background:{{ $preview['colors']['secondary'] ?? '#ccc' }}"></span>
            </div>
            <div class="muted" style="font-size:12px;margin-top:6px">{{ $preview['colors']['primary'] ?? '—' }} · {{ $preview['colors']['secondary'] ?? '—' }}</div>
        </div>
        <div>
            <span class="badge ok">{{ $preview['lifecycle'] }}</span>
            <div class="muted" style="font-size:13px;margin-top:6px">
                Asset: {{ $preview['assets']['icons'] }} icone · {{ $preview['assets']['splash'] }} splash{{ $preview['assets']['version'] > 0 ? ' · v'.$preview['assets']['version'] : '' }}
            </div>
        </div>
    </div>
</div>

<div class="grid cols-2">
    <div class="card">
        <h2>Identità store</h2>
        <table>
            <tr><td class="muted">App</td><td><strong>{{ $brand?->app_name ?? $project->store_name }}</strong></td></tr>
            <tr><td class="muted">Store name</td><td>{{ $project->store_name }}</td></tr>
            <tr><td class="muted">Bundle ID</td><td><code>{{ $project->bundle_id }}</code></td></tr>
            <tr><td class="muted">Package</td><td><code>{{ $project->package_name }}</code></td></tr>
            <tr><td class="muted">Slug</td><td>{{ $project->slug }}</td></tr>
            @php
                $sm = [
                    'draft' => ['off', 'Bozza'], 'ready' => ['warn', 'Pronta'], 'generated' => ['ok', 'Generata'],
                    'ready_to_build' => ['ok', 'Pronta build'], 'building' => ['warn', 'In build'],
                    'published' => ['ok', 'Pubblicata'], 'failed' => ['danger', 'Fallita'],
                ];
                [$sc, $sl] = $sm[$project->build_status->value] ?? ['off', $project->build_status->value];
            @endphp
            <tr><td class="muted">Stato build</td><td><span class="badge {{ $sc }}">{{ $sl }}</span></td></tr>
            <tr><td class="muted">Powered by</td><td>{{ $project->powered_by_enabled ? 'sì' : 'no' }}</td></tr>
        </table>
        <p class="muted mt" style="font-size:13px">Bundle/package sono immutabili dopo la prima pubblicazione.</p>
    </div>

    <div class="card">
        <h2>Template</h2>
        <form method="post" action="{{ route('control.apps.template', $project->uuid) }}">
            @csrf @method('PUT')
            <label for="template_code">Template app</label>
            <select id="template_code" name="template_code">
                @foreach ($templates as $code => $tpl)
                    <option value="{{ $code }}" @selected($project->template_code === $code)>{{ $tpl['label'] }}</option>
                @endforeach
            </select>
            <button class="btn mt" type="submit">Salva template</button>
        </form>

        <h2 class="mt">Genera pacchetto</h2>
        <p class="muted" style="margin-top:0">Produce il manifest di build (config + asset). Nessuna pubblicazione.</p>
        <form method="post" action="{{ route('control.apps.generate', $project->uuid) }}">
            @csrf
            <button class="btn" type="submit">Genera pacchetto</button>
        </form>
        @if ($project->build_status->value !== 'draft')
            <a class="btn secondary mt" href="{{ route('control.apps.package', $project->uuid) }}">Scarica package (ZIP)</a>
        @endif
    </div>
</div>

<div class="card">
    <h2>Build / pacchetti</h2>
    @if ($builds->isEmpty())
        <div class="empty">Nessun pacchetto generato.</div>
    @else
        <table>
            <tr><th>Versione</th><th>Piattaforma</th><th>Stato</th><th>Quando</th><th></th></tr>
            @foreach ($builds as $build)
                <tr>
                    <td>{{ $build->version }}</td>
                    <td class="muted">{{ $build->platform }}</td>
                    @php $bc = ['failed' => 'danger', 'building' => 'warn', 'published' => 'ok', 'built' => 'ok'][$build->status] ?? 'off'; @endphp
                    <td><span class="badge {{ $bc }}">{{ $build->status }}</span></td>
                    <td class="muted">{{ $build->created_at?->format('d/m/Y H:i') }}</td>
                    <td>
                        @if ($build->platform === 'config')
                            <a class="btn small secondary" href="{{ route('control.apps.download', [$project->uuid, $build->uuid]) }}">Scarica manifest</a>
                        @elseif ($build->artifact_path)
                            <code class="muted" style="font-size:12px">{{ $build->artifact_path }}</code>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

@endsection
