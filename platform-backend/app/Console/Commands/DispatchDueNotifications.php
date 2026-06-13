<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\Notifications\Application\SendNotificationJob;
use App\Modules\Notifications\Infrastructure\Models\NotificationRecord;
use Illuminate\Console\Command;

/**
 * Hourly safety-net sweep (docs/29 §3): promotes due outbox rows to queue
 * jobs. Catches rows beyond the direct-dispatch horizon and any delayed job
 * lost to a Redis failover — the send job is idempotent, double dispatch is
 * harmless.
 */
class DispatchDueNotifications extends Command
{
    protected $signature = 'notifications:dispatch-due {--chunk=500}';

    protected $description = 'Queue delivery jobs for due, still-scheduled notification records';

    public function handle(CurrentTenant $currentTenant): int
    {
        $dispatched = 0;

        $currentTenant->bypass(function () use (&$dispatched): void {
            NotificationRecord::query()
                ->where('status', NotificationRecord::STATUS_SCHEDULED)
                ->where('scheduled_for', '<=', now())
                ->orderBy('id')
                ->chunkById((int) $this->option('chunk'), function ($records) use (&$dispatched): void {
                    foreach ($records as $record) {
                        SendNotificationJob::dispatch($record->id, $record->tenant_id);
                        $dispatched++;
                    }
                });
        });

        $this->info("Dispatched {$dispatched} due notification(s).");

        return self::SUCCESS;
    }
}
