<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Channels;

use App\Models\Device;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\Notifications\Application\Channels\ChannelDeliveryFailed;
use App\Modules\Notifications\Application\Channels\NotificationChannel;
use App\Modules\Notifications\Application\Channels\RecipientUnreachable;
use App\Modules\Notifications\Application\FcmMessageBuilder;
use App\Modules\Notifications\Application\TemplateRenderer;
use App\Modules\Notifications\Infrastructure\Models\NotificationRecord;
use App\Modules\Customers\Infrastructure\Models\Customer;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * FCM HTTP v1 channel. Authenticates with a Google service account
 * (JWT-bearer grant, token cached until expiry) and removes stale device
 * tokens on UNREGISTERED responses (docs/29 §6).
 */
final readonly class FcmPushChannel implements NotificationChannel
{
    private const OAUTH_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const OAUTH_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_CACHE_KEY = 'fcm:oauth-token';

    public function __construct(
        private HttpFactory $http,
        private Cache $cache,
        private TemplateRenderer $renderer,
        private FcmMessageBuilder $builder,
    ) {
    }

    public function send(NotificationRecord $record): ?string
    {
        $devices = $this->devicesFor($record);

        if ($devices->isEmpty()) {
            throw new RecipientUnreachable('No registered devices for the recipient.');
        }

        $message = $this->renderer->render($record);
        $style = $this->pushStyle();
        $lastMessageId = null;

        foreach ($devices as $device) {
            $lastMessageId = $this->sendToDevice($device, $message, $record, $style);
        }

        return $lastMessageId;
    }

    /**
     * Stile push del tenant corrente (Fase 7): il colore accent default è il
     * primary del brand. Il job lega già il contesto tenant (TenantAwareJob).
     *
     * @return array{color: ?string, priority: string}
     */
    private function pushStyle(): array
    {
        $brand = BrandProfile::query()->first();
        $config = (array) config('branding.default_notification');
        $notification = (array) ($brand?->notification ?? []);

        return [
            'color' => $notification['color'] ?? $config['color'] ?? $brand?->primary_color,
            'priority' => $notification['priority'] ?? $config['priority'] ?? 'high',
        ];
    }

    /** @return \Illuminate\Support\Collection<int, Device> */
    private function devicesFor(NotificationRecord $record)
    {
        $userId = $record->user_id;

        if ($userId === null && $record->customer_id !== null) {
            $userId = Customer::query()->whereKey($record->customer_id)->value('user_id');
        }

        if ($userId === null) {
            return collect();
        }

        return Device::query()->where('user_id', $userId)->get();
    }

    /**
     * @param  array{title: string, body: string}  $message
     * @param  array{color: ?string, priority: string}  $style
     */
    private function sendToDevice(Device $device, array $message, NotificationRecord $record, array $style): ?string
    {
        $projectId = (string) config('services.fcm.project_id');

        $response = $this->http
            ->withToken($this->accessToken())
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => $this->builder->build($device->fcm_token, $message, $record, $style),
            ]);

        if ($response->status() === 404 || str_contains($response->body(), 'UNREGISTERED')) {
            $device->delete(); // stale token hygiene

            return null;
        }

        if ($response->failed()) {
            throw new ChannelDeliveryFailed("FCM responded {$response->status()}.");
        }

        return $response->json('name');
    }

    private function accessToken(): string
    {
        return (string) $this->cache->remember(self::TOKEN_CACHE_KEY, 3000, function (): string {
            $credentialsPath = (string) config('services.fcm.credentials_path');

            if (! is_file($credentialsPath)) {
                throw new ChannelDeliveryFailed('FCM service account credentials are not configured.');
            }

            /** @var array{client_email: string, private_key: string} $account */
            $account = json_decode((string) file_get_contents($credentialsPath), true);

            $now = time();

            $assertion = JWT::encode([
                'iss' => $account['client_email'],
                'scope' => self::OAUTH_SCOPE,
                'aud' => self::OAUTH_ENDPOINT,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $account['private_key'], 'RS256');

            $response = $this->http->asForm()->post(self::OAUTH_ENDPOINT, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if ($response->failed()) {
                throw new ChannelDeliveryFailed('FCM OAuth token request failed.');
            }

            return (string) $response->json('access_token');
        });
    }
}
