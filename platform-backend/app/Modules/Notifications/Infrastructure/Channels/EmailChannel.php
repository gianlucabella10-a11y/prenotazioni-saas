<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Channels;

use App\Modules\Customers\Infrastructure\Models\Customer;
use App\Modules\Notifications\Application\Channels\NotificationChannel;
use App\Modules\Notifications\Application\Channels\RecipientUnreachable;
use App\Modules\Notifications\Application\TemplateRenderer;
use App\Modules\Notifications\Infrastructure\Models\NotificationRecord;
use Illuminate\Contracts\Mail\Mailer;

/**
 * Email fallback channel via the framework mailer (SES in production,
 * docs/21). Plain transactional layout; tenant-branded templates are a
 * Pro-plan iteration on top of the same renderer.
 */
final readonly class EmailChannel implements NotificationChannel
{
    public function __construct(
        private Mailer $mailer,
        private TemplateRenderer $renderer,
    ) {
    }

    public function send(NotificationRecord $record): ?string
    {
        $email = $this->recipientEmail($record);

        if ($email === null) {
            throw new RecipientUnreachable('The recipient has no email address.');
        }

        $message = $this->renderer->render($record);

        $this->mailer->raw($message['body'], function ($mail) use ($email, $message): void {
            $mail->to($email)->subject($message['title']);
        });

        return null; // the mailer abstraction does not expose provider ids
    }

    private function recipientEmail(NotificationRecord $record): ?string
    {
        if ($record->customer_id !== null) {
            return Customer::query()->whereKey($record->customer_id)->value('email');
        }

        if ($record->user_id !== null) {
            return \App\Models\User::query()->whereKey($record->user_id)->value('email');
        }

        return null;
    }
}
