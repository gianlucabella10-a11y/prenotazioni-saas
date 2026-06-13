<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Notification outbox row (docs/29): the DB is the source of truth, queue
 * jobs are derived from it. Status only advances on an actual send, making
 * the send job idempotent and the hourly sweep loss-proof.
 */
class NotificationRecord extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED_NO_CONSENT = 'skipped_no_consent';
    public const STATUS_SKIPPED_NO_CHANNEL = 'skipped_no_channel';
    public const STATUS_OBSOLETE = 'obsolete';

    public const CHANNEL_PUSH = 'push';
    public const CHANNEL_EMAIL = 'email';

    public const MAX_ATTEMPTS = 3;

    protected $table = 'notification_records';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function isDue(): bool
    {
        return $this->scheduled_for->isPast();
    }

    public function isMarketing(): bool
    {
        return $this->campaign_id !== null;
    }
}
