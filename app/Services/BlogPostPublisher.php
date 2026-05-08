<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\ContentTemplate;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class BlogPostPublisher
{
    public function __construct(
        private GitSyncService $git,
    ) {}

    /**
     * Render the same HTML file content used for repo export (escaped body).
     */
    public function renderHtml(BlogPost $post): string
    {
        if ($post->template_id) {
            $template = ContentTemplate::find($post->template_id);

            if ($template) {
                return $template->render([
                    'title' => e($post->title),
                    'body' => nl2br(e($post->body)),
                    'excerpt' => e($post->excerpt ?? ''),
                    'featured_image' => e($post->featured_image ?? ''),
                    'date' => $post->published_at?->format('F j, Y') ?? now()->format('F j, Y'),
                    'tags' => implode(', ', $post->tags ?? []),
                    'seo_title' => e($post->seo_title ?? $post->title),
                    'seo_description' => e($post->seo_description ?? $post->excerpt ?? ''),
                ]);
            }
        }

        $title = e($post->title);
        $seoTitle = e($post->seo_title ?? $post->title);
        $seoDesc = e($post->seo_description ?? $post->excerpt ?? '');
        $date = $post->published_at?->format('F j, Y') ?? now()->format('F j, Y');
        $image = $post->featured_image ? '<img src="'.e($post->featured_image).'" alt="'.$title.'">' : '';
        $tagHtml = implode('', array_map(fn ($t) => '<span class="tag">'.e($t).'</span>', $post->tags ?? []));
        $bodyHtml = nl2br(e($post->body));

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
                <div class="content">{$bodyHtml}</div>
            </article>
        </body>
        </html>
        HTML;
    }

    /**
     * Write rendered HTML into the site repo and push. No-op if repo is not cloned.
     *
     * @throws \Throwable When the file write or git push fails.
     */
    public function writeToRepository(Site $site, BlogPost $post, string $commitMessage): void
    {
        if (! $this->git->isCloned($site)) {
            return;
        }

        $outputPath = $post->output_path ?? "blog/{$post->slug}.html";
        $repoPath = rtrim((string) $site->repo_path, '/\\');
        $fullPath = "{$repoPath}/{$outputPath}";

        // Reject traversal segments before any filesystem interaction.
        // A non-existent parent directory causes realpath() to return false,
        // which previously made the guard fall back to the repo root and allow
        // traversal paths through unchanged.
        if (str_contains($outputPath, '..')) {
            throw new \RuntimeException("Refusing to write blog post output outside of repository: {$outputPath}");
        }

        // Secondary guard via realpath for symlink resolution.
        $realRepo = realpath($repoPath);
        if ($realRepo !== false) {
            $realDir = realpath(dirname($fullPath));
            if ($realDir === false) {
                $realDir = realpath($repoPath);
            }
            if ($realDir !== false && ! str_starts_with($realDir, $realRepo.DIRECTORY_SEPARATOR) && $realDir !== $realRepo) {
                throw new \RuntimeException("Refusing to write blog post output outside of repository: {$outputPath}");
            }
        }

        File::ensureDirectoryExists(dirname($fullPath));

        $html = $this->renderHtml($post);
        $originalContent = File::exists($fullPath) ? File::get($fullPath) : null;

        File::put($fullPath, $html);

        try {
            $post->update(['output_path' => $outputPath]);

            $this->git->commitAndPush(
                $site,
                [$outputPath],
                $commitMessage
            );
        } catch (\Throwable $e) {
            if ($originalContent === null) {
                File::delete($fullPath);
            } else {
                File::put($fullPath, $originalContent);
            }

            throw $e;
        }
    }

    /**
     * Log a repo sync failure without rethrowing (for non-interactive callers).
     */
    public function logRepositoryFailure(Site $site, BlogPost $post, \Throwable $e): void
    {
        Log::warning('Blog post repository sync failed.', [
            'site_id' => $site->id,
            'post_id' => $post->id,
            'error' => $e->getMessage(),
        ]);
    }
}
