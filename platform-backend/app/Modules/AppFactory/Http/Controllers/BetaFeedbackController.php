<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Http\Controllers;

use App\Models\User;
use App\Modules\AppFactory\Infrastructure\Models\BetaFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Raccolta feedback dei beta tester dall'app (FASE 6). Il tenant è quello del
 * contesto corrente (risolto da tenant.key); il feedback è legato all'utente
 * autenticato. Tenant-scoped via BelongsToTenant.
 */
final class BetaFeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:2000'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'platform' => ['nullable', 'in:android,ios'],
        ]);

        BetaFeedback::query()->create([
            'user_id' => $user->id,
            'app_version' => $data['app_version'] ?? null,
            'platform' => $data['platform'] ?? null,
            'message' => $data['message'],
        ]);

        return response()->json(['status' => 'ok'], 201);
    }
}
