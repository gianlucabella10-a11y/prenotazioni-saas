<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Infrastructure\Models\NotificationRecord;
use Illuminate\Contracts\Translation\Translator;

/**
 * Renders a notification record into a localized title/body pair from the
 * lang/<locale>/notifications.php catalog. The recipient's locale wins over
 * the tenant default (docs/29 §2).
 */
final readonly class TemplateRenderer
{
    public function __construct(private Translator $translator)
    {
    }

    /** @return array{title: string, body: string} */
    public function render(NotificationRecord $record): array
    {
        $locale = (string) ($record->payload['locale'] ?? config('app.locale'));
        $replacements = $this->replacements($record);

        return [
            'title' => $this->line("notifications.{$record->template_code}.title", $replacements, $locale),
            'body' => $this->line("notifications.{$record->template_code}.body", $replacements, $locale),
        ];
    }

    /** @return array<string, string> */
    private function replacements(NotificationRecord $record): array
    {
        $payload = $record->payload;

        return [
            'app_name' => (string) ($payload['app_name'] ?? ''),
            'customer_name' => (string) ($payload['customer_name'] ?? ''),
            'service_name' => (string) ($payload['service_name'] ?? ''),
            'local_time' => (string) ($payload['local_time'] ?? ''),
            'location_name' => (string) ($payload['location_name'] ?? ''),
        ];
    }

    /** @param array<string, string> $replacements */
    private function line(string $key, array $replacements, string $locale): string
    {
        $line = $this->translator->get($key, $replacements, $locale);

        return is_string($line) ? $line : $key;
    }
}
