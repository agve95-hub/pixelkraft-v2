<?php

namespace App\Http\Controllers;

use App\Jobs\SyncFromWebhookJob;
use App\Models\Site;
use App\Models\WebhookDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle incoming GitHub webhook.
     */
    public function github(Request $request, ?Site $site = null): JsonResponse
    {
        $event = (string) $request->header('X-GitHub-Event', '');
        $deliveryId = trim((string) $request->header('X-GitHub-Delivery', ''));

        if ($deliveryId === '') {
            return response()->json(['error' => 'Missing delivery id'], 400);
        }

        // Verify signature based on environment/config.
        $secret = config('pixelkraft.github_webhook_secret');
        $mustVerifySignature = (bool) config('pixelkraft.github_webhook_require_signature', ! app()->environment('local'));

        if ($mustVerifySignature && ! $secret) {
            Log::error('GitHub webhook secret is required but missing', [
                'event' => $event,
                'delivery_id' => $deliveryId,
            ]);

            return response()->json(['error' => 'Webhook receiver is not configured'], 503);
        }

        if ($mustVerifySignature) {
            $signature = $request->header('X-Hub-Signature-256');

            if (! $this->verifyGitHubSignature($request->getContent(), $signature, $secret)) {
                Log::warning('GitHub webhook signature verification failed', [
                    'event' => $event,
                    'delivery_id' => $deliveryId,
                ]);

                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        $payload = $request->all();
        $repository = Site::normalizeGithubRepository($payload['repository']['full_name'] ?? '');

        if (! $repository) {
            return response()->json(['error' => 'Missing repository info'], 400);
        }

        if (! $this->recordDelivery('github', $deliveryId, $event, $repository, $request, $site)) {
            return response()->json(['status' => 'duplicate', 'delivery_id' => $deliveryId]);
        }

        Log::info("GitHub webhook received: {$event}", [
            'repo' => $repository,
            'delivery_id' => $deliveryId,
        ]);

        // We only care about push events.
        if ($event !== 'push') {
            return response()->json(['status' => 'ignored', 'event' => $event]);
        }

        $ref = (string) ($payload['ref'] ?? '');
        $branch = str_starts_with($ref, 'refs/heads/')
            ? substr($ref, strlen('refs/heads/'))
            : '';

        if ($branch === '') {
            return response()->json(['error' => 'Missing branch ref'], 400);
        }

        $sites = Site::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Site $site) => $site->normalizedGithubRepository() === $repository)
            ->values();

        if ($site) {
            $sites = $sites->where('id', $site->id)->values();
        }

        if ($sites->isEmpty()) {
            Log::info("No matching site found for repo [{$repository}]");

            return response()->json(['status' => 'no_matching_site']);
        }

        $rawBody = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        $dispatched = 0;
        foreach ($sites as $site) {
            if ($branch !== $site->branch) {
                Log::info("Webhook push to [{$branch}] ignored for site [{$site->slug}] (configured: {$site->branch})");

                continue;
            }

            // Per-site secret verification — if the site has its own webhook_secret,
            // the incoming signature must also be valid for that secret.  This gives
            // each connected repo an independent signing key so a leaked global secret
            // cannot be used to trigger a specific site's deployment.
            if (! empty($site->webhook_secret)) {
                if (! $this->verifyGitHubSignature($rawBody, $signature, $site->webhook_secret)) {
                    Log::warning('Per-site webhook signature verification failed, skipping dispatch.', [
                        'site' => $site->slug,
                        'delivery_id' => $deliveryId,
                    ]);

                    continue;
                }
            }

            SyncFromWebhookJob::dispatch($site, $payload, $deliveryId);
            $dispatched++;

            Log::info("Dispatched SyncFromWebhookJob for [{$site->slug}]");
        }

        return response()->json([
            'status' => 'ok',
            'dispatched' => $dispatched,
        ]);
    }

    /**
     * Verify GitHub webhook signature (HMAC SHA-256).
     */
    private function verifyGitHubSignature(string $payload, ?string $signature, string $secret): bool
    {
        if (! $signature) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    private function recordDelivery(
        string $provider,
        string $deliveryId,
        string $event,
        string $repository,
        Request $request,
        ?Site $site = null,
    ): bool {
        try {
            WebhookDelivery::create([
                'provider' => $provider,
                'delivery_id' => $deliveryId,
                'event' => $event,
                'repository' => $repository,
                'site_id' => $site?->id,
                'status' => 'received',
                'headers' => $this->sanitizedWebhookHeaders($request),
                'payload' => $this->minimalGithubWebhookPayload($request->all()),
                'received_at' => now(),
            ]);

            return true;
        } catch (QueryException $e) {
            // Duplicate provider+delivery_id pair.
            if ((string) $e->getCode() === '23000') {
                Log::info('Duplicate webhook delivery ignored', [
                    'provider' => $provider,
                    'delivery_id' => $deliveryId,
                ]);

                return false;
            }

            throw $e;
        }
    }

    /**
     * Store only non-sensitive headers useful for debugging (no signatures or auth).
     *
     * @return array<string, string>
     */
    private function sanitizedWebhookHeaders(Request $request): array
    {
        $all = collect($request->headers->all())
            ->map(fn ($value) => implode(', ', $value));

        $keep = ['x-github-event', 'x-github-delivery', 'user-agent', 'content-type', 'accept'];

        return $all->only($keep)->all();
    }

    /**
     * Persist a minimal push payload for dedupe / audit — not the full GitHub JSON
     * (which can contain emails, tokens in commit messages, etc.).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function minimalGithubWebhookPayload(array $payload): array
    {
        $head = is_array($payload['head_commit'] ?? null) ? $payload['head_commit'] : [];
        $message = isset($head['message']) && is_string($head['message'])
            ? mb_substr($head['message'], 0, 500)
            : null;

        return [
            'ref' => $payload['ref'] ?? null,
            'before' => $payload['before'] ?? null,
            'after' => $payload['after'] ?? null,
            'repository' => [
                'full_name' => is_array($payload['repository'] ?? null)
                    ? ($payload['repository']['full_name'] ?? null)
                    : null,
            ],
            'pusher' => [
                'name' => is_array($payload['pusher'] ?? null)
                    ? ($payload['pusher']['name'] ?? null)
                    : null,
            ],
            'head_commit' => [
                'id' => $head['id'] ?? null,
                'timestamp' => $head['timestamp'] ?? null,
                'message' => $message,
            ],
        ];
    }
}
