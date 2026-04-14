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

    public function test_extended_contact_fields_are_stored_and_used_for_inbox(): void
    {
        $site = Site::create([
            'name' => 'Extended Form Site',
            'slug' => 'extended-form-site',
            'repo_url' => 'https://github.com/example/ext.git',
            'branch' => 'main',
            'is_active' => true,
        ]);

        $this->postJson('/api/forms/extended-form-site', [
            '_form_name' => 'lead',
            'first_name' => 'Pat',
            'last_name' => 'Lee',
            'email' => 'pat@example.com',
            'company' => 'Acme Co',
            'phone' => '+15551234567',
            'inquiry' => 'We need a quote for a new site.',
            'department' => 'sales',
        ])->assertCreated();

        $submission = FormSubmission::query()->where('site_id', $site->id)->firstOrFail();
        $stored = $submission->data;
        $this->assertSame('Pat', $stored['first_name']);
        $this->assertSame('Acme Co', $stored['company']);
        $this->assertStringContainsString('quote', $stored['inquiry']);

        $inbox = SiteInboxMessage::query()->where('site_id', $site->id)->where('source', 'form')->firstOrFail();
        $this->assertSame('Pat Lee', $inbox->from_name);
        $this->assertStringContainsString('We need a quote', $inbox->body);
    }

    public function test_unknown_form_fields_are_not_persisted(): void
    {
        $site = Site::create([
            'name' => 'Strict Site',
            'slug' => 'strict-form-site',
            'repo_url' => 'https://github.com/example/strict.git',
            'branch' => 'main',
            'is_active' => true,
        ]);

        $this->postJson('/api/forms/strict-form-site', [
            'message' => 'Hi',
            'extra_field' => 'should not be stored',
        ])->assertCreated();

        $submission = FormSubmission::query()->where('site_id', $site->id)->firstOrFail();
        $this->assertArrayNotHasKey('extra_field', $submission->data);
        $this->assertSame('Hi', $submission->data['message']);
    }

    public function test_per_form_config_strips_fields_not_in_subset(): void
    {
        config([
            'pixelkraft.form_submission_allowed_fields' => [
                '*' => [
                    'email',
                    'name',
                    'message',
                    'company',
                    'phone',
                ],
                'minimal' => ['email', 'message'],
            ],
        ]);

        $site = Site::create([
            'name' => 'Subset Site',
            'slug' => 'subset-form-site',
            'repo_url' => 'https://github.com/example/subset.git',
            'branch' => 'main',
            'is_active' => true,
        ]);

        $this->postJson('/api/forms/subset-form-site', [
            '_form_name' => 'minimal',
            'email' => 'x@example.com',
            'name' => 'Should Drop',
            'message' => 'Only this and email.',
            'company' => 'Also drop',
        ])->assertCreated();

        $submission = FormSubmission::query()->where('site_id', $site->id)->firstOrFail();
        $stored = $submission->data;
        $this->assertSame('minimal', $submission->form_name);
        $this->assertSame('x@example.com', $stored['email']);
        $this->assertSame('Only this and email.', $stored['message']);
        $this->assertArrayNotHasKey('name', $stored);
        $this->assertArrayNotHasKey('company', $stored);
    }

    public function test_to_email_is_stored_on_inbox_when_provided(): void
    {
        $site = Site::create([
            'name' => 'Routed Site',
            'slug' => 'routed-form-site',
            'repo_url' => 'https://github.com/example/routed.git',
            'branch' => 'main',
            'is_active' => true,
        ]);

        $this->postJson('/api/forms/routed-form-site', [
            'email' => 'sender@example.com',
            'message' => 'Please route this.',
            'to_email' => 'sales@client.example',
        ])->assertCreated();

        $inbox = SiteInboxMessage::query()->where('site_id', $site->id)->where('source', 'form')->firstOrFail();
        $this->assertSame('sales@client.example', $inbox->to_email);
    }

    public function test_honeypot_still_detected_when_hp_not_in_stored_allowlist(): void
    {
        config([
            'pixelkraft.form_submission_allowed_fields' => [
                '*' => ['email', 'message'],
            ],
        ]);

        $site = Site::create([
            'name' => 'Hp Allow Site',
            'slug' => 'hp-allow-site',
            'repo_url' => 'https://github.com/example/hp.git',
            'branch' => 'main',
            'is_active' => true,
        ]);

        $this->postJson('/api/forms/hp-allow-site', [
            'email' => 'bot@example.com',
            'message' => 'Hi',
            '_hp' => 'filled',
        ])->assertCreated();

        $submission = FormSubmission::query()->where('site_id', $site->id)->firstOrFail();
        $this->assertTrue($submission->is_spam);
        $this->assertArrayNotHasKey('_hp', $submission->data);
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
