<?php

declare(strict_types=1);

/**
 * Notification templates, Italian. Placeholders are whitelisted by
 * TemplateRenderer: :app_name :customer_name :service_name :local_time
 * :location_name
 */
return [
    'booking_confirmed' => [
        'title' => 'Prenotazione confermata',
        'body' => 'Ciao :customer_name, il tuo appuntamento per :service_name è confermato per il :local_time presso :location_name.',
    ],
    'booking_requested' => [
        'title' => 'Richiesta inviata',
        'body' => 'Ciao :customer_name, la tua richiesta per :service_name il :local_time è in attesa di conferma.',
    ],
    'booking_reminder' => [
        'title' => 'Promemoria appuntamento',
        'body' => 'Ti aspettiamo il :local_time presso :location_name per :service_name.',
    ],
    'booking_cancelled_by_tenant' => [
        'title' => 'Appuntamento annullato',
        'body' => 'Il tuo appuntamento del :local_time per :service_name è stato annullato. Apri l\'app per riprogrammarlo.',
    ],
];
