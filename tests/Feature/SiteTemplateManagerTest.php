<?php

namespace Tests\Feature;

use App\Models\ContentTemplate;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteTemplateManagerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'tmpl@example.com'): User
    {
        return User::create([
            'name' => 'Tmpl User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user, string $slug = 'tmpl-site'): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Tmpl Site',
            'slug' => $slug,
            'repo_url' => 'https://github.com/example/tmpl',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    public function test_templates_page_loads(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->get("/dashboard/sites/{$site->id}/templates")
            ->assertOk();
    }

    public function test_owner_can_create_template(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->postJson("/dashboard/sites/{$site->id}/templates", [
                'name' => 'Monthly Newsletter',
                'type' => 'newsletter',
                'html_template' => '<p>Hello {{name}}</p>',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('content_templates', [
            'site_id' => $site->id,
            'name' => 'Monthly Newsletter',
            'type' => 'newsletter',
        ]);
    }

    public function test_template_name_is_required(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->postJson("/dashboard/sites/{$site->id}/templates", [
                'name' => '',
                'type' => 'newsletter',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_owner_can_update_template(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $template = ContentTemplate::create([
            'site_id' => $site->id,
            'name' => 'Old Name',
            'type' => 'blog_post',
            'html_template' => '<p>Old</p>',
        ]);

        $this->actingAs($user)
            ->putJson("/dashboard/sites/{$site->id}/templates/{$template->id}", [
                'name' => 'New Name',
                'type' => 'newsletter',
            ])
            ->assertRedirect();

        $fresh = $template->fresh();
        $this->assertSame('New Name', $fresh->name);
        $this->assertSame('newsletter', $fresh->type);
    }

    public function test_owner_can_delete_template(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $template = ContentTemplate::create([
            'site_id' => $site->id,
            'name' => 'Deletable',
            'html_template' => '<p>Del</p>',
        ]);

        $this->actingAs($user)
            ->deleteJson("/dashboard/sites/{$site->id}/templates/{$template->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('content_templates', ['id' => $template->id]);
    }

    public function test_user_cannot_update_another_sites_template(): void
    {
        $owner = $this->makeUser('owner@t.com');
        $other = $this->makeUser('other@t.com');

        $site = $this->makeSite($owner, 'owner-tmpl-site');
        $template = ContentTemplate::create(['site_id' => $site->id, 'name' => 'Private', 'html_template' => '<p>x</p>']);

        $this->actingAs($other)
            ->putJson("/dashboard/sites/{$site->id}/templates/{$template->id}", [
                'name' => 'Hacked',
            ])
            ->assertStatus(404);

        $this->assertSame('Private', $template->fresh()->name);
    }

    public function test_user_cannot_delete_another_sites_template(): void
    {
        $owner = $this->makeUser('owner2@t.com');
        $other = $this->makeUser('other2@t.com');

        $site = $this->makeSite($owner, 'owner-tmpl-site-2');
        $template = ContentTemplate::create(['site_id' => $site->id, 'name' => 'Safe', 'html_template' => '<p>x</p>']);

        $this->actingAs($other)
            ->deleteJson("/dashboard/sites/{$site->id}/templates/{$template->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('content_templates', ['id' => $template->id]);
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->get("/dashboard/sites/{$site->id}/templates")
            ->assertRedirect('/login');
    }
}
