<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CloneRepoJob;
use App\Jobs\DeploySiteJob;
use App\Jobs\ParseSiteJob;
use App\Models\DeployLog;
use App\Models\Site;
use App\Services\DeployService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(): JsonResponse
    {
        $sites = Site::query()
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
        ParseSiteJob::dispatch($site);

        return response()->json([
            'status'  => 'dispatched',
            'message' => "Sync job dispatched for {$site->name}",
        ]);
    }

    public function deploy(Site $site): JsonResponse
    {
        DeploySiteJob::dispatch($site, 'api');

        return response()->json([
            'status'  => 'dispatched',
            'message' => "Deploy job dispatched for {$site->name}",
        ]);
    }

    public function rollback(Site $site, string $logId): JsonResponse
    {
        $log = DeployLog::where('site_id', $site->id)->findOrFail($logId);

        if (empty($log->snapshot_tag)) {
            return response()->json(['error' => 'No snapshot available for this deploy'], 400);
        }

        $deployer = app(DeployService::class);
        $result = $deployer->rollback($site, $log->snapshot_tag);

        return response()->json([
            'status'  => $result->status,
            'message' => "Rollback to {$log->snapshot_tag}: {$result->status}",
        ]);
    }

    public function pages(Site $site): JsonResponse
    {
        $pages = $site->pages()
            ->orderBy('url_path')
            ->get()
            ->map(fn ($page) => [
                'id'               => $page->id,
                'file_path'        => $page->file_path,
                'url_path'         => $page->url_path,
                'title'            => $page->title,
                'seo_score'        => $page->seo_score,
                'lighthouse_score' => $page->lighthouse_score,
                'is_published'     => $page->is_published,
                'content_hash'     => $page->content_hash,
                'updated_at'       => $page->updated_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $pages]);
    }

    public function deploys(Site $site): JsonResponse
    {
        $deploys = $site->deployLogs()
            ->latest('created_at')
            ->limit(25)
            ->get()
            ->map(fn ($log) => [
                'id'             => $log->id,
                'status'         => $log->status,
                'commit_sha'     => $log->commit_sha,
                'commit_message' => $log->commit_message,
                'duration_ms'    => $log->duration_ms,
                'triggered_by'   => $log->triggered_by,
                'snapshot_tag'   => $log->snapshot_tag,
                'created_at'     => $log->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $deploys]);
    }

    private function formatSite(Site $site, bool $detailed = false): array
    {
        $data = [
            'id'             => $site->id,
            'name'           => $site->name,
            'slug'           => $site->slug,
            'domain'         => $site->domain,
            'project_type'   => $site->project_type,
            'deploy_status'  => $site->deploy_status,
            'ssl_status'     => $site->ssl_status,
            'is_active'      => $site->is_active,
            'pages_count'    => $site->pages_count ?? 0,
            'last_deployed_at' => $site->last_deployed_at?->toIso8601String(),
            'last_synced_at'   => $site->last_synced_at?->toIso8601String(),
        ];

        if ($site->latestUptimeCheck) {
            $data['uptime'] = [
                'is_up'            => $site->latestUptimeCheck->is_up,
                'response_time_ms' => $site->latestUptimeCheck->response_time_ms,
                'checked_at'       => $site->latestUptimeCheck->checked_at?->toIso8601String(),
            ];
        }

        if ($detailed) {
            $data['repo_url']         = $site->repo_url;
            $data['branch']           = $site->branch;
            $data['build_command']    = $site->build_command;
            $data['build_output_dir'] = $site->build_output_dir;
            $data['blog_posts_count'] = $site->blog_posts_count ?? 0;
            $data['products_count']   = $site->product_listings_count ?? 0;
            $data['submissions_count'] = $site->form_submissions_count ?? 0;
        }

        return $data;
    }
}
