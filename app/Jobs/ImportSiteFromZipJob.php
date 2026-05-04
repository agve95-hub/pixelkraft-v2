<?php

namespace App\Jobs;

use App\Enums\DeployStatus;
use App\Models\Notification;
use App\Models\Site;
use App\Services\Import\ImportException;
use App\Services\Import\ZipImportService;
use App\Services\ProjectDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportSiteFromZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  string  $zipDiskPath  Path on the local disk returned by UploadedFile::store()
     */
    public function __construct(
        public readonly Site $site,
        public readonly string $zipDiskPath,
    ) {
        $this->onQueue('default');
    }

    public function handle(ZipImportService $importer, ProjectDetector $detector): void
    {
        Log::info("ImportSiteFromZipJob started for [{$this->site->slug}]");

        $this->site->update(['deploy_status' => DeployStatus::Deploying]);

        try {
            $result = $importer->import($this->site, $this->zipDiskPath);

            // Confirm / refine project type with the detector now that files exist on disk
            if ($this->site->fresh()->project_type === 'static_html') {
                $detector->applyToSite($this->site->fresh());
            }

            // Parse the site: discover pages, extract editable regions
            ParseSiteJob::dispatch($this->site->fresh());

            $this->site->fresh()->update(['deploy_status' => DeployStatus::Idle]);

            Log::info("ImportSiteFromZipJob completed for [{$this->site->slug}]", [
                'files_imported' => $result->fileCount,
                'project_type' => $result->projectType,
            ]);

        } catch (ImportException $e) {
            Log::warning("ImportSiteFromZipJob rejected invalid ZIP for [{$this->site->slug}]", [
                'reason' => $e->getMessage(),
            ]);

            $this->site->update(['deploy_status' => DeployStatus::Failed]);

            Notification::createAlert(
                type: 'deploy_failed',
                title: "ZIP import rejected for {$this->site->name}",
                body: $e->getMessage(),
                siteId: $this->site->id,
            );

        } catch (\Throwable $e) {
            Log::error("ImportSiteFromZipJob failed for [{$this->site->slug}]", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->site->update(['deploy_status' => DeployStatus::Failed]);

            Notification::createAlert(
                type: 'deploy_failed',
                title: "ZIP import failed for {$this->site->name}",
                body: 'An unexpected error occurred during import. Check application logs for details.',
                siteId: $this->site->id,
            );

            throw $e; // Re-throw so Horizon marks it as failed
        }
    }

    public function tags(): array
    {
        return ['import', "site:{$this->site->id}"];
    }
}
