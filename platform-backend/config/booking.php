<?php

declare(strict_types=1);

/**
 * Default booking policy applied to new locations (docs/30 §9). Every value
 * is tenant-overridable from the dashboard; nothing here is hardcoded in
 * business logic.
 */
return [
    'defaults' => [
        'booking_window_days' => 60,
        'cancellation_cutoff_minutes' => 1440, // 24h
        'min_notice_minutes' => 60,
        'slot_granularity_minutes' => 15,
    ],

    'default_settings' => [
        'reminder_offsets_hours' => [24],
        'booking_confirmation_mode' => 'auto_confirm',
    ],

    // Default weekly opening applied at provisioning, refined in onboarding.
    'default_weekly_schedule' => [
        // ISO weekday 0 = Monday
        ['weekday' => 1, 'start' => '09:00', 'end' => '19:00'],
        ['weekday' => 2, 'start' => '09:00', 'end' => '19:00'],
        ['weekday' => 3, 'start' => '09:00', 'end' => '19:00'],
        ['weekday' => 4, 'start' => '09:00', 'end' => '19:00'],
        ['weekday' => 5, 'start' => '09:00', 'end' => '19:00'],
    ],
];
