<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NewsletterSubscriberManagerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'sub@example.com'): User
    {
        return User::create([
            'name' => 'Sub User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Sub Site',
            'slug' => 'sub-site',
            'repo_url' => 'https://github.com/example/sub',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    public function test_subscriber_list_page_loads(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->get("/dashboard/sites/{$site->id}/subscribers")
            ->assertOk();
    }

    public function test_owner_can_add_subscriber(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->postJson("/dashboard/sites/{$site->id}/subscribers", [
                'email' => 'reader@example.com',
                'name' => 'A Reader',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'site_id' => $site->id,
            'email' => 'reader@example.com',
            'name' => 'A Reader',
            'status' => 'active',
        ]);
    }

    public function test_adding_duplicate_email_upserts_not_duplicates(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)->postJson("/dashboard/sites/{$site->id}/subscribers", ['email' => 'dup@example.com']);
        $this->actingAs($user)->postJson("/dashboard/sites/{$site->id}/subscribers", ['email' => 'dup@example.com', 'name' => 'Updated']);

        $this->assertSame(1, NewsletterSubscriber::where('site_id', $site->id)->where('email', 'dup@example.com')->count());
    }

    public function test_invalid_email_is_rejected(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->postJson("/dashboard/sites/{$site->id}/subscribers", ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_owner_can_delete_subscriber(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $sub = NewsletterSubscriber::create([
            'site_id' => $site->id,
            'email' => 'gone@example.com',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->deleteJson("/dashboard/sites/{$site->id}/subscribers/{$sub->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('newsletter_subscribers', ['id' => $sub->id]);
    }

    public function test_user_cannot_delete_subscriber_from_another_site(): void
    {
        $owner = $this->makeUser('owner@x.com');
        $other = $this->makeUser('other@x.com');

        $site = $this->makeSite($owner);
        $sub = NewsletterSubscriber::create(['site_id' => $site->id, 'email' => 'x@x.com', 'status' => 'active']);

        $this->actingAs($other)
            ->deleteJson("/dashboard/sites/{$site->id}/subscribers/{$sub->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $sub->id]);
    }

    public function test_csv_import_adds_subscribers(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $csv = "email,name\ncsv1@example.com,One\ncsv2@example.com,Two\nbad-email,Skip\n";
        $file = UploadedFile::fake()->createWithContent('subs.csv', $csv);

        $this->actingAs($user)
            ->post("/dashboard/sites/{$site->id}/subscribers/import", ['csv' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('newsletter_subscribers', ['site_id' => $site->id, 'email' => 'csv1@example.com', 'name' => 'One']);
        $this->assertDatabaseHas('newsletter_subscribers', ['site_id' => $site->id, 'email' => 'csv2@example.com']);
        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => 'bad-email']);
    }

    public function test_csv_without_email_column_is_rejected(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $csv = "name,phone\nFoo,123\n";
        $file = UploadedFile::fake()->createWithContent('subs.csv', $csv);

        $this->actingAs($user)
            ->post("/dashboard/sites/{$site->id}/subscribers/import", ['csv' => $file])
            ->assertSessionHasErrors(['csv']);
    }
}
