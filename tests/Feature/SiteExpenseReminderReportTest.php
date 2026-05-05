<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Reminder;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteExpenseReminderReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'err@example.com'): User
    {
        return User::create([
            'name' => 'ERR User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user, string $slug = 'err-site'): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'ERR Site',
            'slug' => $slug.'-'.uniqid(),
            'repo_url' => 'https://github.com/example/err',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    // ── Expenses ──────────────────────────────────

    public function test_owner_can_create_expense(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->post(route('sites.expenses.store', $site), [
                'label' => 'Hosting fee',
                'amount' => '12.50',
                'currency' => 'EUR',
                'expense_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'site_id' => $site->id,
            'label' => 'Hosting fee',
            'currency' => 'EUR',
        ]);
    }

    public function test_expense_amount_must_be_positive(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->postJson(route('sites.expenses.store', $site), [
                'label' => 'Test',
                'amount' => '0',
                'currency' => 'EUR',
                'expense_date' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_owner_can_update_expense(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $expense = Expense::create([
            'site_id' => $site->id,
            'label' => 'Old label',
            'amount' => '10.00',
            'currency' => 'EUR',
            'expense_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->put(route('sites.expenses.update', [$site, $expense]), [
                'label' => 'New label',
                'amount' => '20.00',
                'currency' => 'USD',
                'expense_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame('New label', $expense->fresh()->label);
    }

    public function test_owner_can_delete_expense(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $expense = Expense::create([
            'site_id' => $site->id,
            'label' => 'Delete me',
            'amount' => '5.00',
            'currency' => 'EUR',
            'expense_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->delete(route('sites.expenses.destroy', [$site, $expense]))
            ->assertRedirect();

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_owner_can_bulk_delete_expenses(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $e1 = Expense::create(['site_id' => $site->id, 'label' => 'A', 'amount' => '1', 'currency' => 'EUR', 'expense_date' => now()->toDateString()]);
        $e2 = Expense::create(['site_id' => $site->id, 'label' => 'B', 'amount' => '2', 'currency' => 'EUR', 'expense_date' => now()->toDateString()]);

        $this->actingAs($user)
            ->delete(route('sites.expenses.bulk-destroy', $site), ['ids' => [$e1->id, $e2->id]])
            ->assertRedirect();

        $this->assertDatabaseMissing('expenses', ['id' => $e1->id]);
        $this->assertDatabaseMissing('expenses', ['id' => $e2->id]);
    }

    // ── Reminders ─────────────────────────────────

    public function test_owner_can_create_reminder(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->post(route('sites.reminders.store', $site), [
                'title' => 'Renew SSL',
                'due_date' => now()->addDays(30)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reminders', [
            'site_id' => $site->id,
            'title' => 'Renew SSL',
            'is_done' => false,
        ]);
    }

    public function test_owner_can_complete_reminder(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $reminder = Reminder::create([
            'site_id' => $site->id,
            'title' => 'Call client',
            'is_done' => false,
        ]);

        $this->actingAs($user)
            ->post(route('sites.reminders.complete', [$site, $reminder]))
            ->assertRedirect();

        $this->assertTrue((bool) $reminder->fresh()->is_done);
    }

    public function test_owner_can_update_reminder(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $reminder = Reminder::create([
            'site_id' => $site->id,
            'title' => 'Old title',
            'is_done' => false,
        ]);

        $this->actingAs($user)
            ->put(route('sites.reminders.update', [$site, $reminder]), [
                'title' => 'Updated title',
                'due_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame('Updated title', $reminder->fresh()->title);
    }

    public function test_owner_can_delete_reminder(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $reminder = Reminder::create([
            'site_id' => $site->id,
            'title' => 'Gone',
            'is_done' => false,
        ]);

        $this->actingAs($user)
            ->delete(route('sites.reminders.destroy', [$site, $reminder]))
            ->assertRedirect();

        $this->assertDatabaseMissing('reminders', ['id' => $reminder->id]);
    }

    // ── Reports ───────────────────────────────────

    public function test_owner_can_create_report(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->post(route('sites.reports.store', $site), [
                'title' => 'Q1 2026 Summary',
                'report_date' => now()->toDateString(),
                'summary' => 'Traffic increased.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'site_id' => $site->id,
            'title' => 'Q1 2026 Summary',
        ]);
    }

    public function test_owner_can_update_report(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $report = Report::create([
            'site_id' => $site->id,
            'title' => 'Old report',
            'report_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->put(route('sites.reports.update', [$site, $report]), [
                'title' => 'Updated report',
                'report_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame('Updated report', $report->fresh()->title);
    }

    public function test_owner_can_duplicate_report(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $report = Report::create([
            'site_id' => $site->id,
            'title' => 'Monthly Report',
            'report_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->post(route('sites.reports.duplicate', [$site, $report]))
            ->assertRedirect();

        $this->assertSame(2, Report::where('site_id', $site->id)->count());
        $this->assertDatabaseHas('reports', [
            'site_id' => $site->id,
            'title' => 'Monthly Report (copy)',
        ]);
    }

    public function test_owner_can_delete_report(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $report = Report::create([
            'site_id' => $site->id,
            'title' => 'Delete me',
            'report_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->delete(route('sites.reports.destroy', [$site, $report]))
            ->assertRedirect();

        $this->assertDatabaseMissing('reports', ['id' => $report->id]);
    }

    public function test_cross_site_expense_isolation(): void
    {
        $owner = $this->makeUser('own@e.com');
        $other = $this->makeUser('oth@e.com');
        $site = $this->makeSite($owner);

        $expense = Expense::create([
            'site_id' => $site->id,
            'label' => 'Protected',
            'amount' => '10',
            'currency' => 'EUR',
            'expense_date' => now()->toDateString(),
        ]);

        $this->actingAs($other)
            ->delete(route('sites.expenses.destroy', [$site, $expense]))
            ->assertStatus(404);

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }
}
