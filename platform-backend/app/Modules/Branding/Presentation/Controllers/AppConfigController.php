<?php

declare(strict_types=1);

namespace App\Modules\Branding\Presentation\Controllers;

use App\Modules\Branding\Application\BuildWhiteLabelConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * GET /app/config — the white label runtime configuration, with ETag
 * revalidation: clients send If-None-Match and get a 304 on the (very
 * frequent) unchanged case (docs/27 §2).
 */
final class AppConfigController extends Controller
{
    public function show(Request $request, BuildWhiteLabelConfig $builder): JsonResponse|Response
    {
        $config = $builder->execute();

        if ($request->header('If-None-Match') === $config['etag']) {
            return response('', 304)->withHeaders(['ETag' => $config['etag']]);
        }

        return response()
            ->json($config['payload'])
            ->withHeaders([
                'ETag' => $config['etag'],
                'Cache-Control' => 'private, must-revalidate',
            ]);
    }
}
