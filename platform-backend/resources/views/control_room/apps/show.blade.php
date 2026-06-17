@extends('control_room.layout')
@section('title', $tenant->display_name . ' — App')
@section('content')

@if (session('error'))
    <div class="card" style="border-left:4px solid #dc2626"><strong>Errore:</strong> {{ session('error') }}</div>
@endif

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
        @if ($preview['splash_url'])
            <div style="text-align:center">
                <img src="{{ $preview['splash_url'] }}" alt="splash" style="width:54px;height:54px;object-fit:cover;border-radius:10px;border:1px solid #e5e7eb">
                <div class="muted" style="font-size:12px;margin-top:4px">Splash</div>
            </div>
        @endif
        @if ($preview['feature_url'])
            <div style="text-align:center">
                <img src="{{ $preview['feature_url'] }}" alt="feature graphic" style="width:110px;height:54px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb">
                <div class="muted" style="font-size:12px;margin-top:4px">Feature graphic</div>
            </div>
        @endif
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
                Asset: {{ $preview['assets']['icons'] }} icone · {{ $preview['assets']['splash'] }} splash · {{ $preview['assets']['store'] }} store{{ $preview['assets']['version'] > 0 ? ' · v'.$preview['assets']['version'] : '' }}
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
                    'draft' => ['off', 'Bozza'], 'configured' => ['warn', 'Configurata'],
                    'ready' => ['warn', 'Pronta'], 'generated' => ['ok', 'Generata'],
                    'ready_to_build' => ['ok', 'Pronta build'], 'building' => ['warn', 'In build'],
                    'built' => ['ok', 'Compilata'], 'published' => ['ok', 'Pubblicata'], 'failed' => ['danger', 'Fallita'],
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

        @if (in_array($project->build_status->value, ['ready_to_build', 'building', 'built', 'published', 'failed'], true))
            <h2 class="mt">Avvia build</h2>
            <p class="muted" style="margin-top:0">Pipeline: <code>{{ config('app_factory.build_driver') }}</code>. La compilazione nativa gira sul worker/CI.</p>
            <div style="display:flex;gap:8px">
                <form method="post" action="{{ route('control.apps.build', $project->uuid) }}">
                    @csrf <input type="hidden" name="platform" value="android"><button class="btn small" type="submit">Build Android</button>
                </form>
                <form method="post" action="{{ route('control.apps.build', $project->uuid) }}">
                    @csrf <input type="hidden" name="platform" value="ios"><button class="btn small" type="submit">Build iOS</button>
                </form>
            </div>
        @endif
    </div>
</div>

@if (count($assetVersions) > 1)
<div class="card">
    <h2 style="margin-top:0">Versioni asset</h2>
    <p class="muted" style="margin-top:0">Lo storico non viene mai cancellato: ripristina una versione e rigenera il pacchetto per applicarla.</p>
    <table>
        <tr><th>Versione</th><th>Derivati</th><th>Stato</th><th></th></tr>
        @foreach ($assetVersions as $v)
            <tr>
                <td>v{{ $v['version'] }}</td>
                <td class="muted">{{ $v['count'] }}</td>
                <td>@if ($v['current'])<span class="badge ok">corrente</span>@else<span class="badge off">storico</span>@endif</td>
                <td>
                    @unless ($v['current'])
                        <form method="post" action="{{ route('control.apps.rollback', $project->uuid) }}">
                            @csrf <input type="hidden" name="version" value="{{ $v['version'] }}"><button class="btn small secondary" type="submit">Ripristina</button>
                        </form>
                    @endunless
                </td>
            </tr>
        @endforeach
    </table>
</div>
@endif

@if (session('beta_link'))
<div class="card" style="border-left:3px solid #15803d">
    <strong>Link beta (valido 7 giorni)</strong>
    <p class="muted" style="margin:6px 0 0">Invialo all'esercente: apre il download dell'APK da installare sul telefono.</p>
    <code style="display:block;word-break:break-all;font-size:12px;margin-top:6px">{{ session('beta_link') }}</code>
</div>
@endif

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
                    <td>
                        <span class="badge {{ $bc }}">{{ $build->status }}</span>
                        @if ($build->error_message)
                            <div class="muted" style="font-size:11px;color:#dc2626">{{ \Illuminate\Support\Str::limit($build->error_message, 80) }}</div>
                        @endif
                        @if ($build->build_log)
                            <details style="margin-top:4px">
                                <summary class="muted" style="font-size:11px;cursor:pointer">log{{ $build->duration_ms ? ' · '.round($build->duration_ms / 1000, 1).'s' : '' }}{{ ! is_null($build->exit_code) ? ' · exit '.$build->exit_code : '' }}</summary>
                                <pre style="max-height:220px;overflow:auto;font-size:10px;background:#0b1220;color:#cbd5e1;padding:8px;border-radius:6px;white-space:pre-wrap">{{ $build->build_log }}</pre>
                            </details>
                        @endif
                    </td>
                    <td class="muted">{{ $build->created_at?->format('d/m/Y H:i') }}</td>
                    <td>
                        @if ($build->platform === 'config')
                            <a class="btn small secondary" href="{{ route('control.apps.download', [$project->uuid, $build->uuid]) }}">Scarica manifest</a>
                        @elseif ($build->status === 'built' && $build->artifact_path)
                            <form method="post" action="{{ route('control.apps.beta', [$project->uuid, $build->uuid]) }}" style="display:inline">
                                @csrf <button class="btn small" type="submit">Link beta</button>
                            </form>
                            @if ($build->checksum)<div class="muted" style="font-size:10px">sha256:{{ \Illuminate\Support\Str::limit($build->checksum, 16, '…') }}</div>@endif
                        @elseif ($build->artifact_path)
                            <code class="muted" style="font-size:12px">{{ \Illuminate\Support\Str::limit($build->artifact_path, 32) }}</code>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

<div class="card">
    <h2>Beta tester</h2>
    <form method="post" action="{{ route('control.apps.testers.invite', $project->uuid) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        @csrf
        <div><label style="font-size:12px">Nome</label><input type="text" name="name" required></div>
        <div><label style="font-size:12px">Email</label><input type="email" name="email" required></div>
        <div><label style="font-size:12px">Device (opz.)</label><input type="text" name="device"></div>
        <button class="btn small" type="submit">Invita tester</button>
    </form>
    @if ($testers->isEmpty())
        <div class="empty mt">Nessun tester invitato.</div>
    @else
        <table class="mt">
            <tr><th>Nome</th><th>Email</th><th>Device</th><th>Stato</th><th></th></tr>
            @foreach ($testers as $t)
                @php $tc = ['active' => 'ok', 'blocked' => 'danger', 'invited' => 'warn'][$t->status] ?? 'off'; @endphp
                <tr>
                    <td>{{ $t->name }}</td>
                    <td class="muted">{{ $t->email }}</td>
                    <td class="muted">{{ $t->device ?? '—' }}</td>
                    <td><span class="badge {{ $tc }}">{{ $t->status }}</span></td>
                    <td>
                        @if ($t->status !== 'active')
                            <form method="post" action="{{ route('control.apps.testers.update', [$project->uuid, $t->uuid]) }}" style="display:inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="active">
                                <button class="btn small secondary" type="submit">Attiva</button>
                            </form>
                        @endif
                        @if ($t->status !== 'blocked')
                            <form method="post" action="{{ route('control.apps.testers.update', [$project->uuid, $t->uuid]) }}" style="display:inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="blocked">
                                <button class="btn small secondary" type="submit">Blocca</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

<div class="card">
    <h2>Feedback beta</h2>
    @if ($feedback->isEmpty())
        <div class="empty">Nessun feedback dai tester.</div>
    @else
        <table>
            <tr><th>Quando</th><th>Versione</th><th>Messaggio</th></tr>
            @foreach ($feedback as $f)
                <tr>
                    <td class="muted">{{ $f->created_at?->format('d/m/Y H:i') }}</td>
                    <td class="muted">{{ $f->app_version ?? '—' }}{{ $f->platform ? ' · '.$f->platform : '' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($f->message, 120) }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

@endsection
