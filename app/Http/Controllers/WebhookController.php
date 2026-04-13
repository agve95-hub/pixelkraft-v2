<?php

namespace App\Http\Controllers;

use App\Jobs\SyncFromWebhookJob;
use App\Models\Site;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
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

        $dispatched = 0;
        foreach ($sites as $site) {
            if ($branch !== $site->branch) {
                Log::info("Webhook push to [{$branch}] ignored for site [{$site->slug}] (configured: {$site->branch})");
                continue;
            }

            SyncFromWebhookJob::dispatch($site, $payload, $deliveryId);
            $dispatched++;

            Log::info("Dispatched SyncFromWebhookJob for [{$site->slug}]");
        }

        return response()->json([
            'status'     => 'ok',
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

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

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
                'headers' => collect($request->headers->all())
                    ->map(fn ($value) => is_array($value) ? implode(', ', $value) : (string) $value)
                    ->all(),
                'payload' => $request->all(),
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
}
