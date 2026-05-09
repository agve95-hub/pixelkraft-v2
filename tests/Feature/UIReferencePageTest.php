<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UIReferencePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_ui_reference_page_renders_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('system.ui'));

        $response->assertOk()
            ->assertSee('UI system')
            ->assertSee('Buttons and badges')
            ->assertSee('Form rhythm')
            ->assertSee('Data table sample')
            ->assertSee('Deferred components');
    }
}
