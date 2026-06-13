<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application;

use App\Foundation\Tenancy\TenantAwareJob;
use App\Modules\Customers\Infrastructure\Models\Customer;
use App\Modules\Notifications\Application\Channels\ChannelDeliveryFailed;
use App\Modules\Notifications\Application\Channels\NotificationChannel;
use App\Modules\Notifications\Application\Channels\RecipientUnreachable;
use App\Modules\Notifications\Infrastructure\Channels\EmailChannel;
use App\Modules\Notifications\Infrastructure\Channels\FcmPushChannel;
use App\Modules\Notifications\Infrastructure\Models\NotificationRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Idempotent delivery worker (docs/29 §3,7):
 *  - exits when the record is gone, no longer scheduled, or not yet due
 *    (the hourly sweep re-dispatches when due)
 *  - re-checks marketing consent AT SEND TIME, not enqueue time
 *  - tries push first, falls back to email when the recipient is
 *    unreachable on the requested channel
 */
final class SendNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use TenantAwareJob;

    public int $tries = NotificationRecord::MAX_ATTEMPTS;

    public function __construct(public int $notificationId, int $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    public function handle(): void
    {
        $this->bindTenantContext();

        /** @var NotificationRecord|null $record */
        $record = NotificationRecord::query()->find($this->notificationId);

        if ($record === null || $record->status !== NotificationRecord::STATUS_SCHEDULED || ! $record->isDue()) {
            return;
        }

        if ($record->isMarketing() && ! $this->hasMarketingConsent($record)) {
            $record->update(['status' => NotificationRecord::STATUS_SKIPPED_NO_CONSENT]);

            return;
        }

        $record->increment('attempts');

        foreach ($this->channelChain($record) as $channel) {
            try {
                $providerId = $channel->send($record);

                $record->update([
                    'status' => NotificationRecord::STATUS_SENT,
                    'sent_at' => now(),
                    'provider_message_id' => $providerId,
                ]);

                return;
            } catch (RecipientUnreachable) {
                continue; // next channel in the chain
            } catch (ChannelDeliveryFailed $e) {
                if ($record->attempts >= NotificationRecord::MAX_ATTEMPTS) {
                    $record->update([
                        'status' => NotificationRecord::STATUS_FAILED,
                        'failure_reason' => mb_substr($e->getMessage(), 0, 255),
                    ]);

                    return;
                }

                $this->release(60 * $record->attempts); // backoff, stays scheduled

                return;
            }
        }

        $record->update(['status' => NotificationRecord::STATUS_SKIPPED_NO_CHANNEL]);
    }

    /** @return list<NotificationChannel> ordered delivery preference */
    private function channelChain(NotificationRecord $record): array
    {
        return match ($record->channel) {
            NotificationRecord::CHANNEL_PUSH => [app(FcmPushChannel::class), app(EmailChannel::class)],
            NotificationRecord::CHANNEL_EMAIL => [app(EmailChannel::class)],
            default => [],
        };
    }

    private function hasMarketingConsent(NotificationRecord $record): bool
    {
        if ($record->customer_id === null) {
            return false;
        }

        $customer = Customer::query()->find($record->customer_id);

        $kind = $record->channel === NotificationRecord::CHANNEL_EMAIL
            ? \App\Modules\Customers\Infrastructure\Models\Consent::KIND_MARKETING_EMAIL
            : \App\Modules\Customers\Infrastructure\Models\Consent::KIND_MARKETING_PUSH;

        return $customer !== null && $customer->hasConsent($kind);
    }
}
