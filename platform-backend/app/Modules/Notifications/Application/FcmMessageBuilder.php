<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Infrastructure\Models\NotificationRecord;

/**
 * Costruisce il messaggio FCM HTTP v1 (Fase 7 — Push personalizzata). Pura:
 * nessuna I/O, così è testabile isolatamente. Aggiunge il blocco `android`
 * col colore accent del brand e la priorità (heads-up), mantenendo il data
 * payload minimizzato (solo riferimenti, mai dati personali — docs/33 #55).
 *
 * Volutamente NON imposta channel_id/icona/suono custom: richiederebbero asset
 * dichiarati nell'app (build-time) e un channel_id sconosciuto può sopprimere
 * la notifica su Android O+. Il suono resta quello di default del sistema.
 *
 * @phpstan-type PushStyle array{color?: ?string, priority?: ?string}
 */
final readonly class FcmMessageBuilder
{
    /**
     * @param  array{title: string, body: string}  $message
     * @param  array{color?: ?string, priority?: ?string}  $style
     * @return array<string, mixed>
     */
    public function build(string $token, array $message, NotificationRecord $record, array $style): array
    {
        $priority = ($style['priority'] ?? 'high') === 'normal' ? 'normal' : 'high';
        $color = $this->normalizeColor($style['color'] ?? null);

        $androidNotification = [
            'sound' => 'default',
            'notification_priority' => $priority === 'high' ? 'PRIORITY_HIGH' : 'PRIORITY_DEFAULT',
        ];

        if ($color !== null) {
            $androidNotification['color'] = $color;
        }

        return [
            'token' => $token,
            'notification' => [
                'title' => $message['title'],
                'body' => $message['body'],
            ],
            'android' => [
                'priority' => $priority === 'high' ? 'HIGH' : 'NORMAL',
                'notification' => $androidNotification,
            ],
            // Data payload carries references only, never personal data
            // (push minimization — docs/33 #55).
            'data' => [
                'template_code' => $record->template_code,
                'appointment_uuid' => (string) ($record->payload['appointment_uuid'] ?? ''),
            ],
        ];
    }

    /** Colore #RRGGBB valido, altrimenti null (FCM userà il default). */
    private function normalizeColor(?string $color): ?string
    {
        if ($color === null) {
            return null;
        }

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1 ? $color : null;
    }
}
