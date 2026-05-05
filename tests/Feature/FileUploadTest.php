<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'files@example.com'): User
    {
        return User::create([
            'name' => 'Files User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Files Site',
            'slug' => 'files-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/files',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_files_page_loads(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->get(route('sites.files', $site))
            ->assertOk()
            ->assertViewIs('dashboard.sites.files');
    }

    public function test_owner_can_upload_allowed_file_type(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $file = UploadedFile::fake()->create('logo.png', 100, 'image/png');

        $this->actingAs($user)
            ->post(route('sites.files.upload', $site), ['file' => $file])
            ->assertRedirect();

        Storage::disk('public')->assertExists('sites/'.$site->id);
    }

    public function test_upload_rejects_disallowed_mime_type(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        // PHP file — should be rejected
        $file = UploadedFile::fake()->create('shell.php', 1, 'application/x-httpd-php');

        $this->actingAs($user)
            ->post(route('sites.files.upload', $site), ['file' => $file])
            ->assertSessionHasErrors(['file']);
    }

    public function test_upload_rejects_files_over_size_limit(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        // 25 MB — over the 20 MB limit
        $file = UploadedFile::fake()->create('huge.jpg', 25 * 1024, 'image/jpeg');

        $this->actingAs($user)
            ->post(route('sites.files.upload', $site), ['file' => $file])
            ->assertSessionHasErrors(['file']);
    }

    public function test_owner_can_delete_uploaded_file(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $filename = 'test-logo-abc123.png';
        Storage::disk('public')->put('sites/'.$site->id.'/'.$filename, 'fake-content');

        $this->actingAs($user)
            ->delete(route('sites.files.destroy', [$site, $filename]))
            ->assertRedirect();

        Storage::disk('public')->assertMissing('sites/'.$site->id.'/'.$filename);
    }

    public function test_delete_rejects_dotdot_in_filename(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        // The route guard blocks filenames containing '..'
        $this->actingAs($user)
            ->delete(route('sites.files.destroy', [$site, '..secret.pdf']))
            ->assertStatus(422);
    }

    public function test_other_user_cannot_upload_to_site(): void
    {
        $owner = $this->makeUser('owner@f.com');
        $other = $this->makeUser('other@f.com');
        $site = $this->makeSite($owner);

        $file = UploadedFile::fake()->create('logo.png', 100, 'image/png');

        $this->actingAs($other)
            ->post(route('sites.files.upload', $site), ['file' => $file])
            ->assertStatus(404);
    }

    public function test_unauthenticated_cannot_access_files(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->get(route('sites.files', $site))->assertRedirect('/login');
    }
}
