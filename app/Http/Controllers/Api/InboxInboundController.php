<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteInboxMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class InboxInboundController extends Controller
{
    /**
     * Ingest an inbound message (e.g. email forwarding automation, Zapier, custom relay).
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $secret = config('pixelkraft.inbox_inbound_secret');

        if (is_string($secret) && $secret !== '') {
            $token = $request->bearerToken();
            if (! hash_equals($secret, (string) $token)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        $site = Site::where('slug', $slug)->where('is_active', true)->first();

        if (! $site) {
            return response()->json(['error' => 'Site not found'], 404);
        }

        $key = 'inbox-inbound:' . $request->ip() . ':' . $slug;

        if (RateLimiter::tooManyAttempts($key, 30)) {
            return response()->json(['error' => 'Too many requests'], 429);
        }

        RateLimiter::hit($key, 60);

        $validated = $request->validate([
            'from_email' => ['nullable', 'email', 'max:255'],
            'from_name'  => ['nullable', 'string', 'max:255'],
            'to_email'   => ['nullable', 'email', 'max:255'],
            'subject'    => ['required', 'string', 'max:500'],
            'body'       => ['required', 'string', 'max:65535'],
        ]);

        SiteInboxMessage::create([
            'site_id'    => $site->id,
            'direction'  => 'inbound',
            'from_email' => $validated['from_email'] ?? null,
            'from_name'  => $validated['from_name'] ?? null,
            'to_email'   => $validated['to_email'] ?? null,
            'subject'    => $validated['subject'],
            'body'       => $validated['body'],
            'is_read'    => false,
            'source'     => 'webhook',
        ]);

        return response()->json(['status' => 'ok'], 201);
    }
}
