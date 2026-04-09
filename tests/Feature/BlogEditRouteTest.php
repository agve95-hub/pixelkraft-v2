<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogEditRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_edit_route_renders_for_post_owned_by_site(): void
    {
        $user = User::factory()->create();
        $site = Site::create([
            'name' => 'Site One',
            'slug' => 'site-one',
            'repo_url' => 'https://github.com/acme/site-one.git',
            'branch' => 'main',
        ]);

        $post = BlogPost::create([
            'site_id' => $site->id,
            'title' => 'Owned Post',
            'slug' => 'owned-post',
            'body' => 'Body',
        ]);

        $response = $this->actingAs($user)->get(route('blog.edit', [$site, $post]));

        $response->assertOk();
    }

    public function test_blog_edit_route_returns_404_for_post_from_another_site(): void
    {
        $user = User::factory()->create();
        $site = Site::create([
            'name' => 'Site One',
            'slug' => 'site-one-a',
            'repo_url' => 'https://github.com/acme/site-one-a.git',
            'branch' => 'main',
        ]);
        $otherSite = Site::create([
            'name' => 'Site Two',
            'slug' => 'site-two-a',
            'repo_url' => 'https://github.com/acme/site-two-a.git',
            'branch' => 'main',
        ]);

        $foreignPost = BlogPost::create([
            'site_id' => $otherSite->id,
            'title' => 'Foreign Post',
            'slug' => 'foreign-post',
            'body' => 'Body',
        ]);

        $response = $this->actingAs($user)->get(route('blog.edit', [$site, $foreignPost]));

        $response->assertNotFound();
    }
}
