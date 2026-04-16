<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\TrackingScriptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    public function script(Site $site, TrackingScriptService $tracking): Response
    {
        abort_unless($site->is_active, 404);

        return response($tracking->trackerScript($site), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function collect(Request $request, Site $site, TrackingScriptService $tracking): JsonResponse
    {
        abort_unless($site->is_active, 404);

        $payload = $request->validate([
            'event_name' => ['nullable', 'string', 'max:100'],
            'path' => ['nullable', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'visitor_id' => ['nullable', 'string', 'max:120'],
            'session_id' => ['nullable', 'string', 'max:120'],
            // Cap the free-form payload to prevent storage abuse.
            'payload' => ['nullable', 'array', 'max:20'],
            'payload.*' => ['nullable', 'string', 'max:500'],
        ]);

        $tracking->recordEvent($site, $payload, $request);

        return response()->json(['status' => 'ok'], 202);
    }
}
