<?php

namespace Tests\Unit;

use App\Jobs\ActivateReleaseJob;
use App\Jobs\BuildSiteJob;
use App\Jobs\DeploySiteJob;
use App\Jobs\InjectTrackingJob;
use App\Jobs\ProvisionEnvironmentJob;
use App\Models\DeploymentRelease;
use App\Models\DeployLog;
use App\Models\Site;
use App\Services\DeployService;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class DeploySiteJobTest extends TestCase
{
    public function test_it_dispatches_staged_deploy_jobs_after_beginning_the_pipeline(): void
    {
        Bus::fake();

        $site = new Site(['id' => 'site-queued', 'name' => 'Queued Site']);
        $site->id = 'site-queued';

        $log = new DeployLog(['id' => 'log-queued', 'site_id' => $site->id]);
        $log->id = 'log-queued';

        $release = new DeploymentRelease(['id' => 'release-queued', 'site_id' => $site->id]);
        $release->id = 'release-queued';

        $deployer = $this->mock(DeployService::class);
        $deployer->shouldReceive('beginDeployment')
            ->once()
            ->andReturn([
                'log' => $log,
                'release' => $release,
            ]);

        $job = new DeploySiteJob($site, 'manual');
        $job->handle($deployer);

        Bus::assertChained([
            new ProvisionEnvironmentJob($site->id, $log->id, $release->id),
            new BuildSiteJob($site->id, $log->id, $release->id),
            new InjectTrackingJob($site->id, $log->id, $release->id),
            new ActivateReleaseJob($site->id, $log->id, $release->id),
        ]);
    }
}
