@php
    /** @var \App\Modules\TenantManagement\Domain\TenantStatus $status */
    $map = [
        'active' => ['ok', 'Attivo'],
        'at_risk' => ['warn', 'A rischio'],
        'onboarding' => ['warn', 'In attivazione'],
        'suspended' => ['danger', 'Sospeso'],
        'terminated' => ['off', 'Archiviato'],
    ];
    [$cls, $label] = $map[$status->value] ?? ['off', $status->value];
@endphp
<span class="badge {{ $cls }}">{{ $label }}</span>
