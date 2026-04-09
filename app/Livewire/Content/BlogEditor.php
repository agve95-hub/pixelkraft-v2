<?php

namespace App\Livewire\Content;

use App\Models\BlogPost;
use App\Models\ContentTemplate;
use App\Models\Site;
use App\Services\GitSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class BlogEditor extends Component
{
    use WithFileUploads;

    public string $siteId;
    public ?string $postId = null;

    // Post fields
    public string $title = '';
    public string $slug = '';
    public string $body = '';
    public string $excerpt = '';
    public string $featuredImage = '';
    public array $tags = [];
    public string $tagInput = '';
    public string $status = 'draft';
    public ?string $scheduledAt = null;

    // SEO fields
    public string $seoTitle = '';
    public string $seoDescription = '';
    public string $ogImage = '';

    // Template
    public ?string $templateId = null;
    public string $outputPath = '';

    public bool $autoSlug = true;

    protected $rules = [
        'title'          => 'required|string|max:255',
        'slug'           => 'required|string|max:255',
        'body'           => 'required|string',
        'excerpt'        => 'nullable|string|max:500',
        'featuredImage'  => 'nullable|string|max:500',
        'status'         => 'required|in:draft,scheduled,published',
        'scheduledAt'    => 'nullable|date|after:now',
        'seoTitle'       => 'nullable|string|max:70',
        'seoDescription' => 'nullable|string|max:160',
        'outputPath'     => 'nullable|string|max:500',
    ];

    public function mount(): void
    {
        if ($this->postId) {
            $post = BlogPost::findOrFail($this->postId);
            $this->title = $post->title;
            $this->slug = $post->slug;
            $this->body = $post->body;
            $this->excerpt = $post->excerpt ?? '';
            $this->featuredImage = $post->featured_image ?? '';
            $this->tags = $post->tags ?? [];
            $this->status = $post->status;
            $this->scheduledAt = $post->scheduled_at?->format('Y-m-d\TH:i');
            $this->seoTitle = $post->seo_title ?? '';
            $this->seoDescription = $post->seo_description ?? '';
            $this->ogImage = $post->og_image ?? '';
            $this->templateId = $post->template_id;
            $this->outputPath = $post->output_path ?? '';
            $this->autoSlug = false;
        }
    }

    public function updatedTitle(): void
    {
        if ($this->autoSlug) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function addTag(): void
    {
        $tag = trim($this->tagInput);

        if ($tag && ! in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
        }

        $this->tagInput = '';
    }

    public function removeTag(int $index): void
    {
        unset($this->tags[$index]);
        $this->tags = array_values($this->tags);
    }

    public function save(): void
    {
        $this->validate();

        $site = Site::findOrFail($this->siteId);

        $data = [
            'site_id'         => $this->siteId,
            'title'           => $this->title,
            'slug'            => $this->slug,
            'body'            => $this->body,
            'excerpt'         => $this->excerpt ?: null,
            'featured_image'  => $this->featuredImage ?: null,
            'tags'            => $this->tags,
            'status'          => $this->status,
            'scheduled_at'    => $this->status === 'scheduled' ? $this->scheduledAt : null,
            'seo_title'       => $this->seoTitle ?: null,
            'seo_description' => $this->seoDescription ?: null,
            'og_image'        => $this->ogImage ?: null,
            'template_id'     => $this->templateId ?: null,
            'output_path'     => $this->outputPath ?: null,
        ];

        if ($this->status === 'published' && ! $this->postId) {
            $data['published_at'] = now();
        }

        if ($this->postId) {
            $post = BlogPost::findOrFail($this->postId);
            $post->update($data);
        } else {
            $post = BlogPost::create($data);
            $this->postId = $post->id;
            $this->autoSlug = false;
        }

        // Write to repo and push
        $this->writeToRepo($site, $post);

        session()->flash('success', $this->postId ? 'Post updated.' : 'Post created.');
    }

    public function render(): View
    {
        $templates = ContentTemplate::query()
            ->where(fn ($q) => $q->where('site_id', $this->siteId)->orWhereNull('site_id'))
            ->where('type', 'page')
            ->get();

        return view('livewire.content.blog-editor', [
            'templates' => $templates,
        ]);
    }

    private function writeToRepo(Site $site, BlogPost $post): void
    {
        try {
            $git = app(GitSyncService::class);

            if (! $git->isCloned($site)) {
                return;
            }

            // Determine output path
            $outputPath = $post->output_path ?? "blog/{$post->slug}.html";
            $fullPath = "{$site->repo_path}/{$outputPath}";

            File::ensureDirectoryExists(dirname($fullPath));

            $html = $this->renderPost($post, $site);

            File::put($fullPath, $html);

            // Update output path on post
            $post->update(['output_path' => $outputPath]);

            // Commit and push
            $git->commitAndPush(
                $site,
                [$outputPath],
                $this->postId ? "Update post: {$post->title}" : "Add post: {$post->title}"
            );

        } catch (\Throwable $e) {
            session()->flash('error', 'Post saved but failed to push: ' . $e->getMessage());
        }
    }

    private function renderPost(BlogPost $post, Site $site): string
    {
        // Use template if available
        if ($post->template_id) {
            $template = ContentTemplate::find($post->template_id);

            if ($template) {
                return $template->render([
                    'title'          => e($post->title),
                    'body'           => $post->body,
                    'excerpt'        => e($post->excerpt ?? ''),
                    'featured_image' => e($post->featured_image ?? ''),
                    'date'           => $post->published_at?->format('F j, Y') ?? now()->format('F j, Y'),
                    'tags'           => implode(', ', $post->tags ?? []),
                    'seo_title'      => e($post->seo_title ?? $post->title),
                    'seo_description' => e($post->seo_description ?? $post->excerpt ?? ''),
                ]);
            }
        }

        // Fallback: generate basic HTML
        $title = e($post->title);
        $seoTitle = e($post->seo_title ?? $post->title);
        $seoDesc = e($post->seo_description ?? $post->excerpt ?? '');
        $date = $post->published_at?->format('F j, Y') ?? now()->format('F j, Y');
        $image = $post->featured_image ? '<img src="' . e($post->featured_image) . '" alt="' . $title . '">' : '';
        $tagHtml = implode('', array_map(fn ($t) => '<span class="tag">' . e($t) . '</span>', $post->tags ?? []));

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$seoTitle}</title>
            <meta name="description" content="{$seoDesc}">
            <meta property="og:title" content="{$seoTitle}">
            <meta property="og:description" content="{$seoDesc}">
        </head>
        <body>
            <article>
                <h1>{$title}</h1>
                <time>{$date}</time>
                {$image}
                <div class="tags">{$tagHtml}</div>
                <div class="content">{$post->body}</div>
            </article>
        </body>
        </html>
        HTML;
    }
}
