<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Public endpoint consumed by the JavaScript running on managed sites.
 * Returns currently active campaigns (popups) and announcements (banners)
 * for the given site slug.
 *
 * No authentication required — responses are cached for 60 seconds so
 * high-traffic sites don't generate per-request DB queries.
 *
 * Rate-limited at the route level (see routes/api.php).
 */
class ActiveCampaignsController extends Controller
{
    /** Cache TTL in seconds — short enough to feel near-real-time, long enough to protect the DB. */
    private const CACHE_TTL = 60;

    public function __invoke(Site $site): JsonResponse
    {
        $cacheKey = "active-campaigns:{$site->id}";

        $payload = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($site) {
            $campaigns = $site->campaigns()
                ->active()
                ->orderByDesc('priority')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'type' => 'campaign',
                    'headline' => $c->headline,
                    'body' => $c->body,
                    'cta_text' => $c->cta_text,
                    'cta_url' => $c->cta_url,
                    'trigger' => $c->trigger,
                    'trigger_delay_ms' => $c->trigger_delay_ms,
                    'target_pages' => $c->target_pages,
                    'is_dismissible' => $c->is_dismissible,
                    'dismissal_rules' => $c->dismissal_rules,
                    'locale' => $c->locale,
                    'priority' => $c->priority,
                ]);

            $announcements = $site->announcements()
                ->active()
                ->orderByDesc('priority')
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'type' => 'announcement',
                    'message' => $a->message,
                    'style' => $a->style,
                    'cta_text' => $a->cta_text,
                    'cta_url' => $a->cta_url,
                    'placement' => $a->placement,
                    'is_dismissible' => $a->is_dismissible,
                    'locale' => $a->locale,
                    'priority' => $a->priority,
                ]);

            return [
                'campaigns' => $campaigns->values(),
                'announcements' => $announcements->values(),
            ];
        });

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age='.self::CACHE_TTL)
            ->header('Vary', 'Accept-Encoding');
    }
}
