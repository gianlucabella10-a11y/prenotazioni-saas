<?php

declare(strict_types=1);

/** Notification templates, English. See lang/it/notifications.php. */
return [
    'booking_confirmed' => [
        'title' => 'Booking confirmed',
        'body' => 'Hi :customer_name, your appointment for :service_name is confirmed for :local_time at :location_name.',
    ],
    'booking_requested' => [
        'title' => 'Request sent',
        'body' => 'Hi :customer_name, your request for :service_name on :local_time is awaiting confirmation.',
    ],
    'booking_reminder' => [
        'title' => 'Appointment reminder',
        'body' => 'See you on :local_time at :location_name for :service_name.',
    ],
    'booking_cancelled_by_tenant' => [
        'title' => 'Appointment cancelled',
        'body' => 'Your appointment on :local_time for :service_name was cancelled. Open the app to rebook.',
    ],
];
