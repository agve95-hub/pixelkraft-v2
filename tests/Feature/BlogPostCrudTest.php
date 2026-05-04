<?php

namespace Tests\Feature;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BlogPostCrudTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'blog@example.com'): User
    {
        return User::create([
            'name' => 'Blog User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Blog Site',
            'slug' => 'blog-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/blog',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    public function test_blog_index_loads(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->get(route('blog.index', $site))
            ->assertOk()
            ->assertViewIs('dashboard.content.blog-index');
    }

    public function test_blog_index_passes_posts_to_view(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        BlogPost::create([
            'site_id' => $site->id,
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'status' => BlogPostStatus::Draft,
        ]);

        $this->actingAs($user)
            ->get(route('blog.index', $site))
            ->assertOk()
            ->assertViewHas('posts', fn ($posts) => $posts->count() === 1);
    }

    public function test_blog_create_page_loads(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->get(route('blog.create', $site))
            ->assertOk()
            ->assertViewIs('dashboard.content.blog-create');
    }

    public function test_owner_can_create_draft_post(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->post(route('blog.store', $site), [
                'title' => 'My First Post',
                'slug' => 'my-first-post',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('blog_posts', [
            'site_id' => $site->id,
            'title' => 'My First Post',
            'slug' => 'my-first-post',
            'status' => 'draft',
        ]);
    }

    public function test_owner_can_publish_post(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->post(route('blog.store', $site), [
                'title' => 'Published Post',
                'slug' => 'published-post',
                'status' => 'published',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('blog_posts', [
            'site_id' => $site->id,
            'status' => 'published',
        ]);
    }

    public function test_blog_title_is_required(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->postJson(route('blog.store', $site), ['title' => '', 'slug' => 'x', 'status' => 'draft'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_owner_can_update_post(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $post = BlogPost::create([
            'site_id' => $site->id,
            'title' => 'Old Title',
            'slug' => 'old-title',
            'status' => BlogPostStatus::Draft,
        ]);

        $this->actingAs($user)
            ->put(route('blog.update', [$site, $post]), [
                'title' => 'New Title',
                'slug' => 'new-title',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $this->assertSame('New Title', $post->fresh()->title);
    }

    public function test_owner_can_delete_post(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $post = BlogPost::create([
            'site_id' => $site->id,
            'title' => 'Delete Me',
            'slug' => 'delete-me',
            'status' => BlogPostStatus::Draft,
        ]);

        $this->actingAs($user)
            ->delete(route('blog.destroy', [$site, $post]))
            ->assertRedirect();

        $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
    }

    public function test_user_cannot_access_other_sites_posts(): void
    {
        $owner = $this->makeUser('owner@b.com');
        $other = $this->makeUser('other@b.com');
        $site = $this->makeSite($owner);

        $this->actingAs($other)
            ->get(route('blog.index', $site))
            ->assertStatus(404);
    }
}
