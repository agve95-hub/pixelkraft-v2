<?php

namespace Tests\Feature;

use App\Models\FormSubmission;
use App\Models\Notification;
use App\Models\Site;
use App\Models\SiteInboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormSubmissionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_404_for_unknown_slug(): void
    {
        $this->postJson('/api/forms/missing-slug', [
            'name' => 'A',
            'email' => 'a@example.com',
            'message' => 'Hi',
        ])->assertNotFound()
            ->assertJson(['error' => 'Site not found']);
    }

    public function test_returns_404_for_inactive_site(): void
    {
        Site::create([
            'name' => 'Off',
            'slug' => 'off-site',
            'repo_url' => 'https://github.com/example/off.git',
            'branch' => 'main',
            'is_active' => false,
        ]);

        $this->postJson('/api/forms/off-site', [
            'message' => 'Hi',
        ])->assertNotFound();
    }

    public function test_accepts_submission_and_creates_inbox_and_notification(): void
    {
        $site = Site::create([
            'name' => 'Form Site',
            'slug' => 'form-site',
            'repo_url' => 'https://github.com/example/form.git',
            'branch' => 'main',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/forms/form-site', [
            '_form_name' => 'contact',
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'message' => 'Hello from the test suite.',
        ]);

        $response->assertCreated()
            ->assertJson(['status' => 'ok']);

        $submission = FormSubmission::query()->where('site_id', $site->id)->firstOrFail();
        $this->assertFalse($submission->is_spam);
        $this->assertSame('contact', $submission->form_name);

        $this->assertTrue(
            SiteInboxMessage::query()->where('site_id', $site->id)->where('source', 'form')->exists()
        );

        $this->assertTrue(
            Notification::query()->where('site_id', $site->id)->where('type', 'form_received')->exists()
        );
    }

    public function test_honeypot_marks_spam_and_skips_inbox(): void
    {
        $site = Site::create([
            'name' => 'Spam Site',
            'slug' => 'spam-site',
            'repo_url' => 'https://github.com/example/spam.git',
            'branch' => 'main',
            'is_active' => true,
        ]);

        $this->postJson('/api/forms/spam-site', [
            '_hp' => 'filled-by-bot',
            'message' => 'Legit looking text',
        ])->assertCreated();

        $submission = FormSubmission::query()->where('site_id', $site->id)->firstOrFail();
        $this->assertTrue($submission->is_spam);

        $this->assertFalse(
            SiteInboxMessage::query()->where('site_id', $site->id)->where('source', 'form')->exists()
        );
    }
}
