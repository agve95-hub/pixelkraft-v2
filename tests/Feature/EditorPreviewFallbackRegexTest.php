<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EditorPreviewFallbackRegexTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_fallback_preview_handles_at_symbol_without_regex_error(): void
    {
        $repoPath = storage_path('framework/testing/disks/'.Str::uuid());
        $sourceFile = 'app/events-workshops/feuerlauf/anmeldung/page.tsx';
        $absoluteSourcePath = $repoPath.'/'.$sourceFile;

        @mkdir(dirname($absoluteSourcePath), 0777, true);
        file_put_contents($absoluteSourcePath, <<<'TSX'
export default function Page() {
    return <main><h1>Anmeldung</h1><p>@feuerlauf</p></main>;
}
TSX);

        $user = User::create([
            'name' => 'Preview Tester',
            'email' => 'preview@example.test',
            'password' => 'secret',
            'role' => 'admin',
        ]);

        $site = Site::create([
            'name' => 'Preview Site',
            'slug' => 'preview-site',
            'repo_url' => 'https://github.com/acme/preview-site.git',
            'branch' => 'main',
            'project_type' => 'nextjs',
            'repo_path' => $repoPath,
        ]);

        $page = Page::create([
            'site_id' => $site->id,
            'file_path' => $sourceFile,
            'url_path' => '/events-workshops/feuerlauf/anmeldung',
        ]);

        $response = $this->actingAs($user)->get(route('editor.preview', [
            'site' => $site->id,
            'page' => $page->id,
        ]));

        $response->assertOk();
        $response->assertSee('Source fallback preview', false);
        $response->assertSee('@feuerlauf', false);
        $response->assertDontSee('Preview failed', false);
        $response->assertDontSee('Unknown modifier', false);
    }
}
