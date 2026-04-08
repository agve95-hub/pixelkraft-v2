<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Root route should redirect guests to login.
     */
    public function test_the_root_route_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    /**
     * The legacy add-site route should redirect to the unified sites workspace.
     */
    public function test_sites_create_redirects_to_sites_index_anchor(): void
    {
        $user = new \App\Models\User();
        $user->id = 'test-user';

        $response = $this->actingAs($user)->get('/dashboard/sites/create');

        $response->assertRedirect(route('sites.index') . '#add-site');
    }
}
