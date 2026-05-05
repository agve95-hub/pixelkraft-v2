<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteImportAndPdfTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'import@example.com'): User
    {
        return User::create([
            'name' => 'Import User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Import Site',
            'slug' => 'import-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/import',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    // ── ZIP Import ────────────────────────────────

    public function test_zip_upload_is_rejected_for_non_zip_files(): void
    {
        Queue::fake();

        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $badFile = UploadedFile::fake()->create('shell.php', 10, 'application/x-httpd-php');

        $this->actingAs($user)
            ->postJson(route('sites.import.zip', $site), ['file' => $badFile])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        Queue::assertNothingPushed();
    }

    public function test_zip_upload_accepts_valid_zip(): void
    {
        Queue::fake();
        Storage::fake('public');

        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $zipFile = UploadedFile::fake()->create('site.zip', 100, 'application/zip');

        $this->actingAs($user)
            ->post(route('sites.import.zip', $site), ['file' => $zipFile])
            ->assertOk()
            ->assertJsonStructure(['status', 'siteId', 'message']);
    }

    public function test_import_status_returns_current_state(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->getJson(route('sites.import-status', $site))
            ->assertOk()
            ->assertJsonStructure(['status', 'lastDeployedAt', 'deployLog']);
    }

    public function test_zip_import_requires_auth(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->post(route('sites.import.zip', $site))->assertRedirect('/login');
    }

    public function test_other_user_cannot_import_to_site(): void
    {
        Queue::fake();
        Storage::fake('local');

        $owner = $this->makeUser('own@imp.com');
        $other = $this->makeUser('oth@imp.com');
        $site = $this->makeSite($owner);

        $zipFile = UploadedFile::fake()->create('site.zip', 100, 'application/zip');

        $this->actingAs($other)
            ->post(route('sites.import.zip', $site), ['file' => $zipFile])
            ->assertStatus(404);

        Queue::assertNothingPushed();
    }

    // ── Invoice PDF ───────────────────────────────

    public function test_invoice_pdf_returns_pdf_content(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $invoice = Invoice::create([
            'site_id' => $site->id,
            'number' => 'PDF-001',
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'EUR',
            'status' => 'unpaid',
            'tax_rate' => 0,
            'discount_percent' => 0,
            'payment_terms' => 'net30',
        ]);

        $response = $this->actingAs($user)
            ->get(route('sites.invoices.pdf', [$site, $invoice]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_other_user_cannot_download_invoice_pdf(): void
    {
        $owner = $this->makeUser('own@pdf.com');
        $other = $this->makeUser('oth@pdf.com');
        $site = $this->makeSite($owner);

        $invoice = Invoice::create([
            'site_id' => $site->id,
            'number' => 'PDF-002',
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'EUR',
            'status' => 'unpaid',
            'tax_rate' => 0,
            'discount_percent' => 0,
            'payment_terms' => 'net30',
        ]);

        $this->actingAs($other)
            ->get(route('sites.invoices.pdf', [$site, $invoice]))
            ->assertStatus(404);
    }
}
