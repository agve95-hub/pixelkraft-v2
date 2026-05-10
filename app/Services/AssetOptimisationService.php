<?php

namespace App\Services;

use App\Models\DeployLog;
use App\Models\DeploymentRelease;
use App\Models\Site;
use App\Services\Deployment\DeploymentAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles post-build asset optimisation (image compression, HTML/CSS/JS minification,
 * lazy-loading injection) and tracking script injection.
 *
 * Extracted from DeployService so that asset-processing concerns are isolated and
 * independently testable without spinning up the full deploy pipeline.
 */
class AssetOptimisationService
{
    public function __construct(
        private ImageOptimizer $imageOptimizer,
        private HtmlMinifier $minifier,
        private TrackingScriptService $tracking,
    ) {}

    /**
     * Optimise images, minify HTML/CSS/JS, and inject lazy-loading attributes
     * in the build artifact directory.
     *
     * Each sub-step is individually wrapped in a try/catch so a single corrupt
     * file cannot abort the entire deploy.
     */
    public function optimizeAssets(Site $site, DeployLog $log, DeploymentAdapter $adapter): void
    {
        $outputDir = $adapter->artifactDirectory($site);

        if ($adapter->mode() === SiteRuntimeService::MODE_RUNTIME) {
            $log->appendLog('  Skipping static post-processing for runtime-managed build output.');

            return;
        }

        if (! $outputDir || ! File::isDirectory($outputDir)) {
            $log->appendLog('  No output directory found, skipping optimization.');

            return;
        }

        try {
            $imageCount = $this->imageOptimizer->optimizeDirectory($outputDir);
            $log->appendLog("  Optimized {$imageCount} images.");
        } catch (Throwable $e) {
            Log::warning("Image optimization failed for [{$site->slug}] — continuing.", ['error' => $e->getMessage()]);
            $log->appendLog('  Image optimization skipped (error: '.$e->getMessage().').');
        }

        if (! $adapter->supportsAggressiveOptimization($site)) {
            $log->appendLog('  Skipping HTML/JS minification for framework-managed output.');

            return;
        }

        try {
            $minifiedCount = $this->minifier->minifyDirectory($outputDir);
            $log->appendLog("  Minified {$minifiedCount} files.");
        } catch (Throwable $e) {
            Log::warning("HTML minification failed for [{$site->slug}].", ['error' => $e->getMessage()]);
            $log->appendLog('  Minification skipped (error: '.$e->getMessage().').');
        }

        try {
            $lazyCount = $this->minifier->injectLazyLoading($outputDir);
            $log->appendLog("  Injected lazy loading on {$lazyCount} images.");
        } catch (Throwable $e) {
            Log::warning("Lazy loading injection failed for [{$site->slug}].", ['error' => $e->getMessage()]);
            $log->appendLog('  Lazy loading injection skipped (error: '.$e->getMessage().').');
        }
    }

    /**
     * Inject the platform tracking script (and optional GA/GTM snippets) into
     * all HTML files in the build artifact directory.
     */
    public function injectTracking(Site $site, DeployLog $log, DeploymentRelease $release, DeploymentAdapter $adapter): void
    {
        $outputDir = $adapter->artifactDirectory($site);

        if (! $outputDir || ! File::isDirectory($outputDir)) {
            $log->appendLog('  No static artifact directory available for tracking injection.');

            return;
        }

        $count = $this->tracking->injectIntoDirectory($site, $outputDir);
        $release->update([
            'tracking_version' => 'universal-tool-v1',
            'artifact_path' => $outputDir,
            'meta' => array_merge($release->meta ?? [], ['tracking_injected_files' => $count]),
        ]);

        $log->appendLog("  Injected tracking into {$count} HTML files.");
    }
}
