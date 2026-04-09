<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Root route should redirect guests to login.
     */
    public function test_the_root_route_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    /**
     * The add-site page renders successfully for authenticated users.
     */
    public function test_sites_create_renders_for_authenticated_users(): void
    {
        $user = new \App\Models\User();
        $user->id = 'test-user';

        $response = $this->actingAs($user)->get('/dashboard/sites/create');

        $response->assertStatus(200);
    }
}
