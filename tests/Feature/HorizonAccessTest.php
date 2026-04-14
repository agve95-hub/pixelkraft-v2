<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HorizonAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_horizon(): void
    {
        $this->get(route('horizon.index'))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_receives_forbidden_from_horizon(): void
    {
        $user = User::create([
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $this->actingAs($user)
            ->get(route('horizon.index'))
            ->assertForbidden();
    }

    public function test_admin_can_open_horizon_dashboard(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($user)
            ->get(route('horizon.index'))
            ->assertOk();
    }
}
