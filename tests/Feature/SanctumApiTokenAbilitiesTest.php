<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SanctumApiTokenAbilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_bearer_token_without_required_ability_is_forbidden(): void
    {
        $user = User::create([
            'name' => 'Tok',
            'email' => 'tok-read@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'tok-site',
            'repo_url' => 'https://github.com/example/t.git',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $plain = $user->createToken('ci', ['pixelkraft:sites:read'])->plainTextToken;

        $this->withToken($plain)
            ->postJson("/api/v1/sites/{$site->id}/deploy")
            ->assertForbidden();
    }

    public function test_bearer_token_with_ability_allows_action(): void
    {
        Queue::fake();

        $user = User::create([
            'name' => 'Tok2',
            'email' => 'tok-deploy@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'S2',
            'slug' => 'tok-site-2',
            'repo_url' => 'https://github.com/example/t2.git',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $plain = $user->createToken('ci', ['pixelkraft:sites:deploy'])->plainTextToken;

        $this->withToken($plain)
            ->postJson("/api/v1/sites/{$site->id}/deploy")
            ->assertOk();
    }

    public function test_wildcard_token_allows_deploy(): void
    {
        Queue::fake();

        $user = User::create([
            'name' => 'Tok3',
            'email' => 'tok-star@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'S3',
            'slug' => 'tok-site-3',
            'repo_url' => 'https://github.com/example/t3.git',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $plain = $user->createToken('ci', ['*'])->plainTextToken;

        $this->withToken($plain)
            ->postJson("/api/v1/sites/{$site->id}/deploy")
            ->assertOk();
    }
}
