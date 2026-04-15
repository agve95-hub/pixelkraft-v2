<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeployStatus;
use App\Http\Controllers\Controller;
use App\Jobs\CloneRepoJob;
use App\Jobs\DeploySiteJob;
use App\Jobs\ParseSiteJob;
use App\Models\DeployLog;
use App\Models\Site;
use App\Services\AnalyticsAggregator;
use App\Services\DeployService;
use App\Services\GitSyncService;
use App\Services\SiteRuntimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(): JsonResponse
    {
        $sites = Site::query()
            ->visibleTo(request()->user())
            ->withCount('pages')
            ->with('latestDeploy', 'latestUptimeCheck')
            ->orderBy('name')
            ->get()
            ->map(fn (Site $site) => $this->formatSite($site));

        return response()->json(['data' => $sites]);
    }

    public function show(Site $site): JsonResponse
    {
        $site->loadCount('pages', 'blogPosts', 'productListings', 'formSubmissions');
        $site->load('latestDeploy', 'latestUptimeCheck');

        return response()->json(['data' => $this->formatSite($site, detailed: true)]);
    }

    public function sync(Site $site): JsonResponse
    {
        if ($site->deploy_status?->isActive()) {
            return response()->json([
                'error' => 'conflict',
                'message' => 'A deploy is currently in progress. Sync will run automatically once the deploy completes.',
                'current_status' => $site->deploy_status->value,
            ], 409);
        }

        if (! app(GitSyncService::class)->isCloned($site)) {
            CloneRepoJob::dispatch($site);

            return response()->json([
                'status' => 'dispatched',
                'message' => "Clone + sync job dispatched for {$site->name}",
            ]);
        }

        ParseSiteJob::dispatch($site);

        return response()->json([
            'status' => 'dispatched',
            'message' => "Sync job dispatched for {$site->name}",
        ]);
    }

    public function deploy(Site $site): JsonResponse
    {
        if ($site->deploy_status?->isActive()) {
            return response()->json([
                'error' => 'conflict',
                'message' => 'A deploy is already in progress for this site.',
                'current_status' => $site->deploy_status->value,
            ], 409);
        }

        DeploySiteJob::dispatch($site, 'api');

        return response()->json([
            'status' => 'dispatched',
            'message' => "Deploy job dispatched for {$site->name}",
        ]);
    }

    public function rollback(Site $site, string $logId): JsonResponse
    {
        $log = DeployLog::query()
            ->where('site_id', $site->id)
            ->findOrFail($logId);

        if (empty($log->snapshot_tag)) {
            return response()->json(['error' => 'No snapshot available for this deploy'], 400);
        }

        try {
            $deployer = app(DeployService::class);
            $result = $deployer->rollback($site, $log);

            return response()->json([
                'status' => $result->status,
                'message' => "Rollback to {$log->snapshot_tag}: {$result->status}",
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'Rollback failed',
                'message' => 'An unexpected error occurred while rolling back this deployment.',
            ], 500);
        }
    }

    public function pages(Site $site, Request $request): JsonResponse
    {
        $perPage = min(100, max(10, (int) $request->query('per_page', 50)));

        $paginator = $site->pages()
            ->orderBy('url_path')
            ->paginate($perPage)
            ->through(fn ($page) => [
                'id' => $page->id,
                'file_path' => $page->file_path,
                'url_path' => $page->url_path,
                'title' => $page->title,
                'seo_score' => $page->seo_score,
                'lighthouse_score' => $page->lighthouse_score,
                'is_published' => $page->is_published,
                'content_hash' => $page->content_hash,
                'updated_at' => $page->updated_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function analytics(Site $site, Request $request, AnalyticsAggregator $analytics): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        return response()->json([
            'data' => [
                'traffic' => $analytics->getSiteStats($site, $days),
                'events' => $analytics->summarizeSiteEvents($site, $days),
                'current_release' => $site->currentDeploymentRelease()->first(),
                'tracking_installation' => $site->activeTrackingInstallation()->first(),
            ],
        ]);
    }

    public function deploys(Site $site): JsonResponse
    {
        $deploys = $site->deployLogs()
            ->latest('created_at')
            ->limit(25)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'status' => $log->status,
                'commit_sha' => $log->commit_sha,
                'commit_message' => $log->commit_message,
                'duration_ms' => $log->duration_ms,
                'triggered_by' => $log->triggered_by,
                'snapshot_tag' => $log->snapshot_tag,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $deploys]);
    }

    public function releases(Site $site): JsonResponse
    {
        $releases = $site->deploymentReleases()
            ->latest('created_at')
            ->limit(25)
            ->get()
            ->map(fn ($release) => [
                'id' => $release->id,
                'status' => $release->status,
                'source_commit_sha' => $release->source_commit_sha,
                'source_branch' => $release->source_branch,
                'artifact_path' => $release->artifact_path,
                'tracking_version' => $release->tracking_version,
                'is_current' => $release->is_current,
                'activated_at' => $release->activated_at?->toIso8601String(),
                'created_at' => $release->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $releases]);
    }

    public function gitOperations(Site $site): JsonResponse
    {
        $operations = $site->gitOperations()
            ->latest('started_at')
            ->limit(50)
            ->get()
            ->map(fn ($operation) => [
                'id' => $operation->id,
                'operation' => $operation->operation,
                'status' => $operation->status,
                'branch' => $operation->branch,
                'working_branch' => $operation->working_branch,
                'commit_sha' => $operation->commit_sha,
                'files' => $operation->files,
                'started_at' => $operation->started_at?->toIso8601String(),
                'completed_at' => $operation->completed_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $operations]);
    }

    private function formatSite(Site $site, bool $detailed = false): array
    {
        $data = [
            'id' => $site->id,
            'name' => $site->name,
            'slug' => $site->slug,
            'domain' => $site->domain,
            'project_type' => $site->project_type,
            'deployment_mode' => app(SiteRuntimeService::class)->deploymentMode($site),
            'deploy_status' => $site->deploy_status,
            'ssl_status' => $site->ssl_status,
            'is_active' => $site->is_active,
            'pages_count' => $site->pages_count ?? 0,
            'last_deployed_at' => $site->last_deployed_at?->toIso8601String(),
            'last_synced_at' => $site->last_synced_at?->toIso8601String(),
        ];

        if ($site->latestUptimeCheck) {
            $data['uptime'] = [
                'is_up' => $site->latestUptimeCheck->is_up,
                'is_degraded' => (bool) $site->latestUptimeCheck->is_degraded,
                'response_time_ms' => $site->latestUptimeCheck->response_time_ms,
                'checked_at' => $site->latestUptimeCheck->checked_at?->toIso8601String(),
            ];
        }

        if ($detailed) {
            $data['repo_url'] = $site->repo_url;
            $data['branch'] = $site->branch;
            $data['build_command'] = $site->build_command;
            $data['build_output_dir'] = $site->build_output_dir;
            $data['blog_posts_count'] = $site->blog_posts_count ?? 0;
            $data['products_count'] = $site->product_listings_count ?? 0;
            $data['submissions_count'] = $site->form_submissions_count ?? 0;
        }

        return $data;
    }
}
