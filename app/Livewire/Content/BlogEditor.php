<?php

namespace App\Livewire\Content;

use App\Models\BlogPost;
use App\Models\ContentTemplate;
use App\Services\BlogPostPublisher;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
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

    private ?string $resolvedSiteId = null;

    protected $rules = [
        'title' => 'required|string|max:255',
        'slug' => 'required|string|max:255',
        'body' => 'required|string',
        'excerpt' => 'nullable|string|max:500',
        'featuredImage' => 'nullable|string|max:500',
        'status' => 'required|in:draft,scheduled,published',
        'scheduledAt' => 'nullable|date|after:now',
        'seoTitle' => 'nullable|string|max:70',
        'seoDescription' => 'nullable|string|max:160',
        // Relative path inside the repo, no .. traversal allowed.
        'outputPath' => ['nullable', 'string', 'max:255', 'not_regex:/\.\.|^\/|[\r\n;{}]/'],
    ];

    public function mount(): void
    {
        $this->resolvedSiteId = SiteAccess::findOrFail($this->siteId)->id;

        if ($this->postId) {
            $post = BlogPost::query()
                ->whereKey($this->postId)
                ->where('site_id', $this->resolvedSiteId)
                ->firstOrFail();
            $this->title = $post->title;
            $this->slug = $post->slug;
            $this->body = $post->body;
            $this->excerpt = $post->excerpt ?? '';
            $this->featuredImage = $post->featured_image ?? '';
            $this->tags = $post->tags ?? [];
            $this->status = $post->status->value;
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

        $site = SiteAccess::findOrFail($this->siteId);
        $this->resolvedSiteId = $site->id;

        $data = [
            'site_id' => $this->resolvedSiteId,
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => $this->body,
            'excerpt' => $this->excerpt ?: null,
            'featured_image' => $this->featuredImage ?: null,
            'tags' => $this->tags,
            'status' => $this->status,
            'scheduled_at' => $this->status === 'scheduled' ? $this->scheduledAt : null,
            'seo_title' => $this->seoTitle ?: null,
            'seo_description' => $this->seoDescription ?: null,
            'og_image' => $this->ogImage ?: null,
            'template_id' => $this->templateId ?: null,
            'output_path' => $this->outputPath ?: null,
        ];

        if ($this->status === 'published' && ! $this->postId) {
            $data['published_at'] = now();
        }

        if ($this->postId) {
            $post = BlogPost::query()
                ->whereKey($this->postId)
                ->where('site_id', $this->resolvedSiteId)
                ->firstOrFail();
            $post->update($data);
        } else {
            $post = BlogPost::create($data);
            $this->postId = $post->id;
            $this->autoSlug = false;
        }

        // Write to repo and push
        try {
            app(BlogPostPublisher::class)->writeToRepository(
                $site,
                $post,
                $this->postId ? "Update post: {$post->title}" : "Add post: {$post->title}"
            );
            session()->flash('success', $this->postId ? 'Post updated.' : 'Post created.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Post saved but failed to push: '.$e->getMessage());
        }
    }

    public function render(): View
    {
        $this->resolvedSiteId ??= SiteAccess::findOrFail($this->siteId)->id;

        $templates = ContentTemplate::query()
            ->where(fn ($q) => $q->where('site_id', $this->resolvedSiteId)->orWhereNull('site_id'))
            ->where('type', 'page')
            ->get();

        return view('livewire.content.blog-editor', [
            'templates' => $templates,
        ]);
    }
}
