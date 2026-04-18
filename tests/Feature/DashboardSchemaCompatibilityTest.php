<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Support\SchemaState;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardSchemaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        SchemaState::reset();
        parent::tearDown();
    }

    public function test_dashboard_still_renders_when_optional_dashboard_schema_is_missing(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Fallback Project',
            'slug' => 'fallback-project',
            'repo_url' => 'https://github.com/example/fallback',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        Page::create([
            'site_id' => $site->id,
            'file_path' => 'index.html',
            'url_path' => '/',
            'title' => 'Home',
            'is_published' => true,
        ]);

        Schema::dropIfExists('site_inbox_messages');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('seo_issues');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('reports');

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('maintenance_settings');
        });

        SchemaState::reset();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Fallback Project', false);
    }
}
