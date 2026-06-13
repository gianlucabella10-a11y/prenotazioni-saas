@extends('dashboard.layout')
@section('title', $service ? 'Modifica servizio' : 'Nuovo servizio')
@section('content')

@php $variant = $service?->variants->firstWhere('is_default', true) ?? $service?->variants->first(); @endphp

<div class="card" style="max-width:640px">
    <form method="post"
          action="{{ $service ? route('dashboard.services.update', $service->uuid) : route('dashboard.services.store') }}">
        @csrf
        @if ($service) @method('PUT') @endif

        <label for="name">Nome *</label>
        <input id="name" type="text" name="name" required maxlength="255"
               value="{{ old('name', $service->name ?? '') }}">

        <label for="category">Categoria</label>
        <input id="category" type="text" name="category" maxlength="255"
               value="{{ old('category', $service?->category?->name ?? '') }}"
               placeholder="es. Taglio, Barba, Trattamenti">

        <label for="description">Descrizione (mostrata nell'app)</label>
        <textarea id="description" name="description" rows="3"
                  maxlength="2000">{{ old('description', $service->description ?? '') }}</textarea>

        <div class="row">
            <div>
                <label for="price">Prezzo (€) *</label>
                <input id="price" type="number" name="price" required min="0" step="0.50"
                       value="{{ old('price', $variant ? number_format($variant->price_cents / 100, 2, '.', '') : '') }}">
            </div>
            <div>
                <label for="duration_minutes">Durata (minuti) *</label>
                <input id="duration_minutes" type="number" name="duration_minutes" required min="5" max="480" step="5"
                       value="{{ old('duration_minutes', $variant->duration_minutes ?? 30) }}">
            </div>
            <div>
                <label for="buffer_after_minutes">Pausa dopo (min)</label>
                <input id="buffer_after_minutes" type="number" name="buffer_after_minutes" min="0" max="120" step="5"
                       value="{{ old('buffer_after_minutes', $variant->buffer_after_minutes ?? 0) }}">
            </div>
        </div>

        <div class="checkbox">
            <input id="is_active" type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $service->is_active ?? true))>
            <label for="is_active" style="margin:0">Prenotabile dall'app</label>
        </div>

        <div class="mt">
            <button class="btn" type="submit">{{ $service ? 'Salva modifiche' : 'Crea servizio' }}</button>
            <a class="btn secondary" href="{{ route('dashboard.services.index') }}">Annulla</a>
        </div>
    </form>
</div>

@endsection
