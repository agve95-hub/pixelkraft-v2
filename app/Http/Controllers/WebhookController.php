<?php

namespace App\Http\Controllers;

use App\Jobs\SyncFromWebhookJob;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle incoming GitHub webhook.
     */
    public function github(Request $request): JsonResponse
    {
        // Verify signature if webhook secret is configured
        $secret = config('pixelkraft.github_webhook_secret');

        if (! $secret && app()->environment('production')) {
            Log::error('GitHub webhook secret is missing in production environment');

            return response()->json(['error' => 'Webhook receiver is not configured'], 503);
        }

        if ($secret) {
            $signature = $request->header('X-Hub-Signature-256');

            if (! $this->verifyGitHubSignature($request->getContent(), $signature, $secret)) {
                Log::warning('GitHub webhook signature verification failed');

                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        $event = $request->header('X-GitHub-Event');
        $payload = $request->all();

        Log::info("GitHub webhook received: {$event}", [
            'repo' => $payload['repository']['full_name'] ?? 'unknown',
        ]);

        // We only care about push events
        if ($event !== 'push') {
            return response()->json(['status' => 'ignored', 'event' => $event]);
        }

        // Find the site by repo URL
        $repoFullName = $payload['repository']['full_name'] ?? null;
        $repoCloneUrl = $payload['repository']['clone_url'] ?? null;
        $ref = $payload['ref'] ?? '';

        if (! $repoFullName) {
            return response()->json(['error' => 'Missing repository info'], 400);
        }

        // Find matching site(s)
        $sites = Site::query()
            ->where('is_active', true)
            ->where(function ($q) use ($repoFullName, $repoCloneUrl) {
                $q->where('repo_url', 'like', "%{$repoFullName}%");
                if ($repoCloneUrl) {
                    $q->orWhere('repo_url', $repoCloneUrl);
                }
            })
            ->get();

        if ($sites->isEmpty()) {
            Log::info("No matching site found for repo [{$repoFullName}]");

            return response()->json(['status' => 'no_matching_site']);
        }

        $dispatched = 0;

        foreach ($sites as $site) {
            // Only sync if the push was to the site's configured branch
            $branch = str_replace('refs/heads/', '', $ref);

            if ($branch !== $site->branch) {
                Log::info("Webhook push to [{$branch}] ignored for site [{$site->slug}] (configured: {$site->branch})");
                continue;
            }

            SyncFromWebhookJob::dispatch($site, $payload);
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
}
