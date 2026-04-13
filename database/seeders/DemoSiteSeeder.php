<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Page;
use App\Models\Reminder;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;
use App\Services\SeoAnalyzer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Optional demo data for local evaluation. Run:
 * php artisan db:seed --class=DemoSiteSeeder
 */
class DemoSiteSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'demo@pixelkraft.local'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
        );

        $site = Site::query()->firstOrCreate(
            ['slug' => 'demo-studio'],
            [
                'user_id' => $user->id,
                'name' => 'Demo Studio',
                'repo_url' => 'https://github.com/example/demo-studio',
                'branch' => 'main',
                'project_type' => 'static_html',
                'deploy_status' => 'idle',
                'ssl_status' => 'pending',
                'domain' => null,
                'is_active' => true,
            ],
        );

        if ($site->user_id === null) {
            $site->update(['user_id' => $user->id]);
        }

        if ($site->pages()->count() === 0) {
            Page::query()->firstOrCreate(
                [
                    'site_id' => $site->id,
                    'file_path' => 'index.html',
                ],
                [
                    'url_path' => '/',
                    'title' => 'Welcome — Demo Studio',
                    'meta_description' => 'A seeded demo page so the dashboard is not empty.',
                    'og_title' => 'Demo Studio',
                    'og_description' => 'Preview of pixelkraft with sample content.',
                    'is_published' => true,
                ],
            );

            Page::query()->firstOrCreate(
                [
                    'site_id' => $site->id,
                    'file_path' => 'about.html',
                ],
                [
                    'url_path' => '/about',
                    'title' => 'About',
                    'meta_description' => null,
                    'is_published' => true,
                ],
            );
        }

        $site->load('pages');
        $analyzer = app(SeoAnalyzer::class);
        foreach ($site->pages as $page) {
            $analyzer->analyze($page->fresh());
        }

        Reminder::query()->firstOrCreate(
            [
                'site_id' => $site->id,
                'title' => 'Prepare monthly client summary',
            ],
            [
                'due_date' => now()->addWeeks(2)->toDateString(),
                'is_done' => false,
                'notes' => 'Created by DemoSiteSeeder. Mark done from Dashboard → site → Reminders.',
            ],
        );

        Report::query()->firstOrCreate(
            [
                'site_id' => $site->id,
                'title' => 'Getting started checklist',
            ],
            [
                'report_date' => now()->toDateString(),
                'summary' => "1. Configure domain and SSL\n2. Connect GitHub and run sync\n3. Run: php artisan pixelkraft:analyze-seo --site=demo-studio",
            ],
        );

        Expense::query()->firstOrCreate(
            [
                'site_id' => $site->id,
                'label' => 'Demo hosting line item',
                'expense_date' => now()->toDateString(),
            ],
            [
                'amount' => 19.99,
                'currency' => 'EUR',
            ],
        );
    }
}
