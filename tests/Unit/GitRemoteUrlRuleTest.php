<?php

namespace Tests\Unit;

use App\Rules\GitRemoteUrl;
use PHPUnit\Framework\TestCase;

class GitRemoteUrlRuleTest extends TestCase
{
    private function passes(string $url): bool
    {
        $failed = false;
        (new GitRemoteUrl)->validate('repoUrl', $url, function () use (&$failed) {
            $failed = true;
        });

        return ! $failed;
    }

    /** @test */
    public function it_accepts_https_github_urls(): void
    {
        $this->assertTrue($this->passes('https://github.com/owner/repo'));
        $this->assertTrue($this->passes('https://github.com/owner/repo.git'));
    }

    /** @test */
    public function it_accepts_https_gitlab_and_bitbucket_urls(): void
    {
        $this->assertTrue($this->passes('https://gitlab.com/owner/repo.git'));
        $this->assertTrue($this->passes('https://bitbucket.org/owner/repo'));
    }

    /** @test */
    public function it_accepts_ssh_git_at_format(): void
    {
        $this->assertTrue($this->passes('git@github.com:owner/repo.git'));
        $this->assertTrue($this->passes('git@gitlab.com:owner/repo.git'));
    }

    /** @test */
    public function it_rejects_http_scheme(): void
    {
        $this->assertFalse($this->passes('http://github.com/owner/repo'));
    }

    /** @test */
    public function it_rejects_file_scheme(): void
    {
        $this->assertFalse($this->passes('file:///etc/passwd'));
        $this->assertFalse($this->passes('file://github.com/owner/repo'));
    }

    /** @test */
    public function it_rejects_unknown_hosts(): void
    {
        $this->assertFalse($this->passes('https://attacker.com/owner/repo'));
        $this->assertFalse($this->passes('https://evil.github.com.attacker.com/owner/repo'));
    }

    /** @test */
    public function it_rejects_localhost_and_private_ips(): void
    {
        $this->assertFalse($this->passes('https://localhost/owner/repo'));
        $this->assertFalse($this->passes('https://127.0.0.1/owner/repo'));
        $this->assertFalse($this->passes('https://192.168.1.1/owner/repo'));
    }

    /** @test */
    public function it_rejects_ssh_with_unknown_host(): void
    {
        $this->assertFalse($this->passes('git@attacker.com:owner/repo.git'));
    }

    /** @test */
    public function it_rejects_url_without_repo_path(): void
    {
        $this->assertFalse($this->passes('https://github.com/'));
        $this->assertFalse($this->passes('https://github.com'));
    }
}
