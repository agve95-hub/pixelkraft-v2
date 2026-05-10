<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Models\User;
use App\Services\NginxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class NginxMaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private string $nginxDir;

    private NginxConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nginxDir = sys_get_temp_dir().'/nginx-maint-test-'.uniqid();
        mkdir($this->nginxDir.'/sites-available', 0755, true);
        mkdir($this->nginxDir.'/sites-enabled', 0755, true);

        config()->set('platform.nginx_sites_path', $this->nginxDir.'/sites-available');

        $this->service = app(NginxConfigService::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->nginxDir);
        parent::tearDown();
    }

    private function makeSite(): Site
    {
        $user = User::create([
            'name' => 'U', 'email' => 'nmm-'.uniqid().'@x.com',
            'password' => Hash::make('secret'), 'role' => 'admin',
        ]);

        return Site::create([
            'user_id' => $user->id, 'name' => 'S',
            'slug' => 'nmm-'.uniqid(), 'domain' => 'test.example.com',
            'branch' => 'main', 'project_type' => 'static_html',
            'deploy_path' => '/var/www/sites/test',
        ]);
    }

    public function test_set_maintenance_mode_writes_config_and_reloads_nginx(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('symlink() requires administrator privileges on Windows');
        }

        $site = $this->makeSite();
        $site->maintenance_settings = ['heading' => 'Down for maintenance', 'message' => 'Back soon.'];
        $site->save();

        Process::fake(['sudo nginx -t*' => Process::result('', '', 0), 'sudo systemctl reload nginx' => Process::result('', '', 0)]);

        $this->service->setMaintenanceMode($site, true);

        $overridePath = $this->nginxDir.'/sites-available/maintenance-'.$site->slug.'.conf';
        $this->assertFileExists($overridePath);
        $contents = file_get_contents($overridePath);
        $this->assertStringContainsString('return 503', $contents);
        $this->assertStringContainsString($site->domain, $contents);
    }

    public function test_set_maintenance_mode_false_removes_config_files(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('symlink() requires administrator privileges on Windows');
        }

        $site = $this->makeSite();

        Process::fake(['sudo nginx -t*' => Process::result('', '', 0), 'sudo systemctl reload nginx' => Process::result('', '', 0)]);

        $this->service->setMaintenanceMode($site, true);
        $this->service->setMaintenanceMode($site, false);

        $overridePath = $this->nginxDir.'/sites-available/maintenance-'.$site->slug.'.conf';
        $this->assertFileDoesNotExist($overridePath);
    }

    public function test_set_maintenance_mode_requires_domain(): void
    {
        $user = User::create([
            'name' => 'U', 'email' => 'nmm2-'.uniqid().'@x.com',
            'password' => Hash::make('secret'), 'role' => 'admin',
        ]);
        $site = Site::create([
            'user_id' => $user->id, 'name' => 'No Domain',
            'slug' => 'nmm2-'.uniqid(), 'branch' => 'main', 'project_type' => 'static_html',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no domain/i');
        $this->service->setMaintenanceMode($site, true);
    }

    public function test_generate_ssl_config_writes_https_server_block(): void
    {
        $site = $this->makeSite();
        $path = $this->service->generateSslConfig($site);

        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertStringContainsString('listen 443 ssl', $contents);
        $this->assertStringContainsString('ssl_certificate', $contents);
        $this->assertStringContainsString('return 301 https://', $contents);
    }

    public function test_remove_config_rejects_path_outside_platform_dir(): void
    {
        $site = $this->makeSite();
        $site->nginx_conf_path = '/etc/nginx/nginx.conf'; // platform's main config — must be rejected
        $site->save();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->removeConfig($site);
    }
}
