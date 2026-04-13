<?php

namespace Tests\Feature;

use App\Livewire\Sites\ExpenseManager;
use App\Models\Expense;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SiteExpenseManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_record_and_delete_expense_for_own_site(): void
    {
        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner-exp@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Paid Site',
            'slug' => 'paid-site',
            'repo_url' => 'https://github.com/example/paid',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        Livewire::actingAs($user)
            ->test(ExpenseManager::class, ['siteId' => $site->id])
            ->set('form_label', 'Hosting')
            ->set('form_amount', '12.50')
            ->set('form_currency', 'eur')
            ->set('form_expense_date', '2026-04-01')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('expenses', [
            'site_id' => $site->id,
            'label' => 'Hosting',
            'currency' => 'EUR',
        ]);

        $expense = Expense::query()->where('site_id', $site->id)->firstOrFail();

        Livewire::actingAs($user)
            ->test(ExpenseManager::class, ['siteId' => $site->id])
            ->call('delete', $expense->id);

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }
}
