@extends('dashboard.layout')
@section('title', 'Servizi')
@section('content')

<div class="card">
    <a class="btn" href="{{ route('dashboard.services.create') }}">+ Nuovo servizio</a>
</div>

<div class="card">
    @if ($services->isEmpty())
        <div class="empty">Nessun servizio: creane uno per renderlo prenotabile dall'app.</div>
    @else
        <table>
            <tr><th>Servizio</th><th>Categoria</th><th>Durata</th><th>Prezzo</th><th>Stato</th><th></th></tr>
            @foreach ($services as $service)
                @php $variant = $service->variants->firstWhere('is_default', true) ?? $service->variants->first(); @endphp
                <tr>
                    <td><strong>{{ $service->name }}</strong></td>
                    <td class="muted">{{ $service->category?->name ?? '—' }}</td>
                    <td>{{ $variant?->duration_minutes }} min
                        @if (($variant?->buffer_after_minutes ?? 0) > 0)
                            <span class="muted">(+{{ $variant->buffer_after_minutes }})</span>
                        @endif
                    </td>
                    <td>{{ number_format(($variant?->price_cents ?? 0) / 100, 2, ',', '.') }} €</td>
                    <td>
                        @if ($service->is_active)
                            <span class="badge ok">Attivo</span>
                        @else
                            <span class="badge off">Disattivato</span>
                        @endif
                    </td>
                    <td style="white-space:nowrap">
                        <a class="btn small secondary" href="{{ route('dashboard.services.edit', $service->uuid) }}">Modifica</a>
                        <form class="inline-form" method="post" action="{{ route('dashboard.services.destroy', $service->uuid) }}"
                              onsubmit="return confirm('Eliminare «{{ $service->name }}»? Lo storico resta disponibile.')">
                            @csrf @method('DELETE')
                            <button class="btn small danger" type="submit">Elimina</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

@endsection
