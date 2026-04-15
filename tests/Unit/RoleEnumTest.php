<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleEnumTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_is_cast_to_role_enum(): void
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'role-cast@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $this->assertInstanceOf(Role::class, $user->role);
        $this->assertSame(Role::Editor, $user->role);
    }

    public function test_is_admin_returns_true_for_admin_role(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'role-admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->assertTrue($user->isAdmin());
    }

    public function test_is_admin_returns_false_for_editor_role(): void
    {
        $user = User::create([
            'name' => 'Editor',
            'email' => 'role-editor@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $this->assertFalse($user->isAdmin());
    }

    public function test_role_label_returns_human_readable_string(): void
    {
        $this->assertSame('Admin', Role::Admin->label());
        $this->assertSame('Editor', Role::Editor->label());
    }

    public function test_admin_role_persists_correctly(): void
    {
        $user = User::create([
            'name' => 'A',
            'email' => 'role-persist@example.com',
            'password' => Hash::make('password'),
            'role' => Role::Admin,
        ]);

        $fresh = $user->fresh();
        $this->assertSame(Role::Admin, $fresh->role);
        $this->assertTrue($fresh->isAdmin());
    }
}
