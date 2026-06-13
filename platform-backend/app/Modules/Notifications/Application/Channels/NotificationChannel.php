<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Channels;

use App\Modules\Notifications\Infrastructure\Models\NotificationRecord;

/**
 * Channel abstraction (docs/29 §1): the domain never talks to FCM/SES/SMS
 * providers directly, avoiding vendor lock-in in business logic.
 */
interface NotificationChannel
{
    /**
     * Deliver the record to its recipient.
     *
     * @return string|null provider message id when available
     *
     * @throws ChannelDeliveryFailed on transient or permanent provider errors
     * @throws RecipientUnreachable when the recipient lacks this channel (no
     *         device token, no email…): the dispatcher falls through to the
     *         next channel instead of failing
     */
    public function send(NotificationRecord $record): ?string;
}
