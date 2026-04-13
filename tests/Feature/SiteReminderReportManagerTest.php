<?php

namespace Tests\Feature;

use App\Livewire\Sites\ReminderManager;
use App\Livewire\Sites\ReportManager;
use App\Models\Reminder;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SiteReminderReportManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_crud_for_own_site(): void
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'u-rem@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'R Site',
            'slug' => 'r-site',
            'repo_url' => 'https://github.com/example/r',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        Livewire::actingAs($user)
            ->test(ReminderManager::class, ['siteId' => $site->id])
            ->set('form_title', 'Check DNS')
            ->set('form_due_date', '2026-05-01')
            ->set('form_notes', 'TXT records')
            ->call('save')
            ->assertHasNoErrors();

        $r = Reminder::query()->where('site_id', $site->id)->firstOrFail();
        $this->assertFalse($r->is_done);

        Livewire::actingAs($user)
            ->test(ReminderManager::class, ['siteId' => $site->id])
            ->call('toggleDone', $r->id);

        $this->assertTrue($r->fresh()->is_done);

        Livewire::actingAs($user)
            ->test(ReminderManager::class, ['siteId' => $site->id])
            ->call('delete', $r->id);

        $this->assertDatabaseMissing('reminders', ['id' => $r->id]);
    }

    public function test_report_create_and_delete(): void
    {
        $user = User::create([
            'name' => 'U2',
            'email' => 'u2-rep@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Rep Site',
            'slug' => 'rep-site',
            'repo_url' => 'https://github.com/example/rep',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        Livewire::actingAs($user)
            ->test(ReportManager::class, ['siteId' => $site->id])
            ->set('form_title', 'Q1 summary')
            ->set('form_report_date', '2026-04-01')
            ->set('form_summary', 'Traffic up.')
            ->call('save')
            ->assertHasNoErrors();

        $rep = Report::query()->where('site_id', $site->id)->firstOrFail();

        Livewire::actingAs($user)
            ->test(ReportManager::class, ['siteId' => $site->id])
            ->call('delete', $rep->id);

        $this->assertDatabaseMissing('reports', ['id' => $rep->id]);
    }
}
