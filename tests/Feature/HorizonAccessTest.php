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

    public function test_non_admin_forbidden_in_local_when_local_bypass_disabled(): void
    {
        $originalEnv = app()['env'] ?? null;
        config(['horizon.allow_local_bypass' => false]);
        app()->instance('env', 'local');

        try {
            $user = User::create([
                'name' => 'Local Editor',
                'email' => 'local-editor@example.com',
                'password' => Hash::make('password'),
                'role' => 'editor',
            ]);

            $this->actingAs($user)
                ->get(route('horizon.index'))
                ->assertForbidden();
        } finally {
            app()->instance('env', $originalEnv);
        }
    }

    public function test_non_admin_allowed_in_local_when_local_bypass_enabled(): void
    {
        $originalEnv = app()['env'] ?? null;
        config(['horizon.allow_local_bypass' => true]);
        app()->instance('env', 'local');

        try {
            $user = User::create([
                'name' => 'Bypass Editor',
                'email' => 'bypass-editor@example.com',
                'password' => Hash::make('password'),
                'role' => 'editor',
            ]);

            $this->actingAs($user)
                ->get(route('horizon.index'))
                ->assertOk();
        } finally {
            app()->instance('env', $originalEnv);
        }
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
