<?php

declare(strict_types=1);

/**
 * Per-sector starter catalogs (docs/31 §3 step 3): real, editable defaults
 * that let a tenant reach a working configuration in minutes. Prices in
 * cents, durations/buffers in minutes.
 */
return [
    'barber' => [
        ['category' => 'Taglio', 'service' => 'Taglio capelli', 'duration' => 30, 'buffer' => 5, 'price' => 1800],
        ['category' => 'Barba', 'service' => 'Rasatura e rifinitura barba', 'duration' => 20, 'buffer' => 5, 'price' => 1200],
        ['category' => 'Taglio', 'service' => 'Taglio + barba', 'duration' => 45, 'buffer' => 5, 'price' => 2800],
    ],
    'hair' => [
        ['category' => 'Taglio', 'service' => 'Taglio e piega', 'duration' => 60, 'buffer' => 10, 'price' => 3500],
        ['category' => 'Colore', 'service' => 'Colore', 'duration' => 90, 'buffer' => 15, 'price' => 6000],
        ['category' => 'Piega', 'service' => 'Piega', 'duration' => 30, 'buffer' => 5, 'price' => 2000],
    ],
    'beauty' => [
        ['category' => 'Viso', 'service' => 'Pulizia viso', 'duration' => 60, 'buffer' => 10, 'price' => 5000],
        ['category' => 'Corpo', 'service' => 'Massaggio rilassante', 'duration' => 50, 'buffer' => 10, 'price' => 5500],
        ['category' => 'Mani', 'service' => 'Manicure', 'duration' => 45, 'buffer' => 5, 'price' => 2500],
    ],
    'dental' => [
        ['category' => 'Igiene', 'service' => 'Igiene dentale', 'duration' => 45, 'buffer' => 15, 'price' => 8000],
        ['category' => 'Visite', 'service' => 'Visita di controllo', 'duration' => 30, 'buffer' => 10, 'price' => 5000],
    ],
    'medical' => [
        ['category' => 'Visite', 'service' => 'Prima visita', 'duration' => 45, 'buffer' => 15, 'price' => 12000],
        ['category' => 'Visite', 'service' => 'Visita di controllo', 'duration' => 30, 'buffer' => 10, 'price' => 8000],
    ],
    'physio' => [
        ['category' => 'Trattamenti', 'service' => 'Seduta fisioterapica', 'duration' => 45, 'buffer' => 15, 'price' => 5500],
        ['category' => 'Valutazione', 'service' => 'Valutazione iniziale', 'duration' => 60, 'buffer' => 15, 'price' => 7000],
    ],
    'consultant' => [
        ['category' => 'Consulenze', 'service' => 'Consulenza', 'duration' => 60, 'buffer' => 15, 'price' => 10000],
    ],
    'other' => [
        ['category' => 'Servizi', 'service' => 'Appuntamento', 'duration' => 30, 'buffer' => 10, 'price' => 3000],
    ],
];
