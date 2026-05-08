<?php

namespace Tests\Unit;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\ContentTemplate;
use App\Models\Site;
use App\Models\User;
use App\Services\BlogPostPublisher;
use App\Services\GitSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BlogPostPublisherTest extends TestCase
{
    use RefreshDatabase;

    private BlogPostPublisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publisher = app(BlogPostPublisher::class);
    }

    private function makePost(array $attrs = []): BlogPost
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'bpp-'.uniqid().'@x.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'bpp-'.uniqid(),
            'repo_url' => 'https://github.com/example/s',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        return BlogPost::create(array_merge([
            'site_id' => $site->id,
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'body' => 'Some content here.',
            'status' => BlogPostStatus::Published,
        ], $attrs));
    }

    // ── renderHtml (no template) ──────────────────

    public function test_render_html_includes_title_in_head(): void
    {
        $post = $this->makePost(['title' => 'My Post Title']);
        $html = $this->publisher->renderHtml($post);

        $this->assertStringContainsString('<title>My Post Title</title>', $html);
    }

    public function test_render_html_includes_h1_with_title(): void
    {
        $post = $this->makePost(['title' => 'Heading']);
        $html = $this->publisher->renderHtml($post);

        $this->assertStringContainsString('<h1>Heading</h1>', $html);
    }

    public function test_render_html_escapes_xss_in_title(): void
    {
        $post = $this->makePost(['title' => '<script>alert("xss")</script>']);
        $html = $this->publisher->renderHtml($post);

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_render_html_escapes_xss_in_body(): void
    {
        $post = $this->makePost(['body' => '<img src=x onerror=alert(1)>']);
        $html = $this->publisher->renderHtml($post);

        // e() encodes angle brackets, making the tag inert in the browser.
        // The raw substring may still appear inside encoded form — that's safe.
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function test_render_html_includes_meta_description(): void
    {
        $post = $this->makePost([
            'seo_description' => 'Great post summary',
        ]);
        $html = $this->publisher->renderHtml($post);

        $this->assertStringContainsString('Great post summary', $html);
        $this->assertStringContainsString('meta name="description"', $html);
    }

    public function test_render_html_includes_body_content(): void
    {
        $post = $this->makePost(['body' => 'This is my content.']);
        $html = $this->publisher->renderHtml($post);

        $this->assertStringContainsString('This is my content.', $html);
    }

    public function test_render_html_converts_newlines_to_br(): void
    {
        $post = $this->makePost(['body' => "Line one\nLine two"]);
        $html = $this->publisher->renderHtml($post);

        $this->assertStringContainsString('<br', $html);
    }

    public function test_render_html_includes_tags(): void
    {
        $post = $this->makePost(['tags' => ['php', 'laravel']]);
        $html = $this->publisher->renderHtml($post);

        $this->assertStringContainsString('php', $html);
        $this->assertStringContainsString('laravel', $html);
    }

    public function test_render_html_escapes_tags(): void
    {
        $post = $this->makePost(['tags' => ['<evil>']]);
        $html = $this->publisher->renderHtml($post);

        $this->assertStringNotContainsString('<evil>', $html);
        $this->assertStringContainsString('&lt;evil&gt;', $html);
    }

    public function test_render_html_includes_featured_image_when_set(): void
    {
        $post = $this->makePost(['featured_image' => 'https://example.com/photo.jpg']);
        $html = $this->publisher->renderHtml($post);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('photo.jpg', $html);
    }

    public function test_render_html_no_img_when_no_featured_image(): void
    {
        $post = $this->makePost(['featured_image' => null]);
        $html = $this->publisher->renderHtml($post);

        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_render_html_is_valid_html_structure(): void
    {
        $post = $this->makePost();
        $html = $this->publisher->renderHtml($post);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('</html>', $html);
        $this->assertStringContainsString('<body>', $html);
        $this->assertStringContainsString('</body>', $html);
    }

    // ── renderHtml (with template) ────────────────

    public function test_render_html_uses_template_when_set(): void
    {
        $user = User::create([
            'name' => 'T',
            'email' => 'tmpl-bpp-'.uniqid().'@x.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'tmpl-bpp-'.uniqid(),
            'repo_url' => 'https://github.com/example/t',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $template = ContentTemplate::create([
            'site_id' => $site->id,
            'name' => 'Blog Template',
            'html_template' => '<article>{{title}}: {{body}}</article>',
        ]);

        $post = BlogPost::create([
            'site_id' => $site->id,
            'title' => 'Template Post',
            'slug' => 'template-post',
            'body' => 'Body content',
            'status' => BlogPostStatus::Published,
            'template_id' => $template->id,
        ]);

        $html = $this->publisher->renderHtml($post);

        $this->assertStringContainsString('<article>', $html);
        $this->assertStringContainsString('Template Post', $html);
        $this->assertStringContainsString('Body content', $html);
    }

    public function test_template_tokens_are_html_escaped(): void
    {
        $user = User::create([
            'name' => 'T2',
            'email' => 'tmpl2-bpp-'.uniqid().'@x.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'tmpl2-bpp-'.uniqid(),
            'repo_url' => 'https://github.com/example/t2',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $template = ContentTemplate::create([
            'site_id' => $site->id,
            'name' => 'XSS Template',
            'html_template' => '<h1>{{title}}</h1>',
        ]);

        $post = BlogPost::create([
            'site_id' => $site->id,
            'title' => '<script>alert(1)</script>',
            'slug' => 'xss-post',
            'status' => BlogPostStatus::Published,
            'template_id' => $template->id,
        ]);

        $html = $this->publisher->renderHtml($post);

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_write_to_repository_restores_existing_file_when_push_fails(): void
    {
        $root = storage_path('framework/testing/blog-publisher-'.uniqid());
        File::ensureDirectoryExists($root.'/blog');
        File::put($root.'/blog/hello-world.html', 'before');

        try {
            $post = $this->makePost(['output_path' => 'blog/hello-world.html']);
            $post->site->update(['repo_path' => $root]);

            $this->mock(GitSyncService::class, function ($mock): void {
                $mock->shouldReceive('isCloned')->andReturn(true);
                $mock->shouldReceive('commitAndPush')->andThrow(new \RuntimeException('remote rejected'));
            });

            $this->expectException(\RuntimeException::class);

            app(BlogPostPublisher::class)->writeToRepository($post->site->fresh(), $post, 'Update post');
        } finally {
            $this->assertSame('before', File::get($root.'/blog/hello-world.html'));
            File::deleteDirectory($root);
        }
    }
}
