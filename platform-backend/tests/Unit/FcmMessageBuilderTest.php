<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Notifications\Application\FcmMessageBuilder;
use App\Modules\Notifications\Infrastructure\Models\NotificationRecord;
use Tests\TestCase;

/**
 * Il builder FCM produce il messaggio v1 con lo stile del brand (Fase 7):
 * colore accent + priorità, data payload minimizzato, nessun channel/icona.
 */
final class FcmMessageBuilderTest extends TestCase
{
    private function record(): NotificationRecord
    {
        $record = new NotificationRecord();
        $record->template_code = 'booking_reminder';
        $record->payload = ['appointment_uuid' => 'appt-123'];

        return $record;
    }

    private function message(): array
    {
        return ['title' => 'Promemoria', 'body' => 'Domani alle 10:00'];
    }

    public function test_applies_brand_color_and_high_priority(): void
    {
        $out = (new FcmMessageBuilder())->build(
            'token-abc',
            $this->message(),
            $this->record(),
            ['color' => '#7C3AED', 'priority' => 'high'],
        );

        self::assertSame('token-abc', $out['token']);
        self::assertSame('Promemoria', $out['notification']['title']);
        self::assertSame('HIGH', $out['android']['priority']);
        self::assertSame('#7C3AED', $out['android']['notification']['color']);
        self::assertSame('PRIORITY_HIGH', $out['android']['notification']['notification_priority']);
        self::assertSame('default', $out['android']['notification']['sound']);

        // Data payload: solo riferimenti, nessun dato personale.
        self::assertSame('booking_reminder', $out['data']['template_code']);
        self::assertSame('appt-123', $out['data']['appointment_uuid']);
        // Nessun channel_id/icona custom (rischio soppressione / build-time).
        self::assertArrayNotHasKey('channel_id', $out['android']['notification']);
    }

    public function test_normal_priority_maps_to_default(): void
    {
        $out = (new FcmMessageBuilder())->build(
            'token-abc',
            $this->message(),
            $this->record(),
            ['color' => null, 'priority' => 'normal'],
        );

        self::assertSame('NORMAL', $out['android']['priority']);
        self::assertSame('PRIORITY_DEFAULT', $out['android']['notification']['notification_priority']);
        // Colore assente → chiave omessa (FCM usa il default).
        self::assertArrayNotHasKey('color', $out['android']['notification']);
    }

    public function test_invalid_color_is_dropped(): void
    {
        $out = (new FcmMessageBuilder())->build(
            'token-abc',
            $this->message(),
            $this->record(),
            ['color' => 'not-a-color', 'priority' => 'high'],
        );

        self::assertArrayNotHasKey('color', $out['android']['notification']);
    }
}
