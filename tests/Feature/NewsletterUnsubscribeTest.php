<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class NewsletterUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsigned_unsubscribe_url_is_rejected(): void
    {
        $site = Site::create([
            'name' => 'NL Site',
            'slug' => 'nl-site',
            'repo_url' => 'https://github.com/example/nl.git',
            'branch' => 'main',
        ]);

        $subscriber = NewsletterSubscriber::create([
            'site_id' => $site->id,
            'email' => 'reader@example.com',
            'name' => 'Reader',
            'status' => 'active',
        ]);

        $this->get("/api/unsubscribe/{$subscriber->id}")
            ->assertForbidden();
    }

    public function test_signed_unsubscribe_marks_subscriber(): void
    {
        $site = Site::create([
            'name' => 'NL Site 2',
            'slug' => 'nl-site-2',
            'repo_url' => 'https://github.com/example/nl2.git',
            'branch' => 'main',
        ]);

        $subscriber = NewsletterSubscriber::create([
            'site_id' => $site->id,
            'email' => 'reader2@example.com',
            'status' => 'active',
        ]);

        $url = URL::signedRoute('api.unsubscribe', ['subscriber' => $subscriber->id]);

        $this->get($url)->assertOk();

        $this->assertSame('unsubscribed', $subscriber->fresh()->status);
    }
}
