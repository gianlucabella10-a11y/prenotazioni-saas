<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Controllers;

use App\Models\Device;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Push foundation (Fase 4): device token lifecycle. Upsert keyed on
 * (user, fcm_token) so re-registrations refresh metadata instead of
 * duplicating; stale tokens are also pruned reactively by FcmPushChannel
 * on UNREGISTERED responses (docs/29 §6).
 */
final class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'platform' => ['required', 'in:ios,android'],
            'fcm_token' => ['required', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:10'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        // Keyed on (user, token): the device always belongs to the
        // authenticated user; tenant_id mirrors the user's tenant so a
        // device can never be registered against another tenant.
        $device = Device::query()->updateOrCreate(
            ['user_id' => $user->id, 'fcm_token' => $data['fcm_token']],
            [
                'tenant_id' => $user->tenant_id,
                'platform' => $data['platform'],
                'locale' => $data['locale'] ?? $user->locale,
                'device_name' => $data['device_name'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
            ],
        );

        return response()->json([
            'data' => [
                'platform' => $device->platform,
                'registered_at' => $device->created_at?->toIso8601ZuluString(),
            ],
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate(['fcm_token' => ['required', 'string', 'max:255']]);

        Device::query()
            ->where('user_id', $user->id)
            ->where('fcm_token', $data['fcm_token'])
            ->delete();

        return response()->json(['status' => 'ok']);
    }
}
