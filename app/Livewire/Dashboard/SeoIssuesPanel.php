<?php

namespace App\Livewire\Dashboard;

use App\Models\Page;
use Livewire\Component;

class SeoIssuesPanel extends Component
{
    public function render()
    {
        $issues = collect();

        $missingDescription = Page::whereNull('meta_description')
            ->orWhere('meta_description', '')
            ->with('site')
            ->limit(5)
            ->get();

        foreach ($missingDescription as $page) {
            $issues->push([
                'severity' => 'warning',
                'message' => 'Missing meta description',
                'site' => $page->site?->name ?? 'Unknown',
                'page' => $page,
            ]);
        }

        $noOg = Page::where(function ($q) {
            $q->whereNull('og_title')->orWhere('og_title', '');
        })->where(function ($q) {
            $q->whereNull('og_image')->orWhere('og_image', '');
        })->with('site')->limit(5)->get();

        foreach ($noOg as $page) {
            if (!$issues->contains(fn ($i) => $i['page']->id === $page->id)) {
                $issues->push([
                    'severity' => 'info',
                    'message' => 'No Open Graph tags',
                    'site' => $page->site?->name ?? 'Unknown',
                    'page' => $page,
                ]);
            }
        }

        $shortTitle = Page::whereNotNull('title')
            ->where('title', '!=', '')
            ->whereRaw('LENGTH(title) < 30')
            ->with('site')
            ->limit(5)
            ->get();

        foreach ($shortTitle as $page) {
            if (!$issues->contains(fn ($i) => $i['page']->id === $page->id)) {
                $issues->push([
                    'severity' => 'warning',
                    'message' => 'Title tag too short (under 30 chars)',
                    'site' => $page->site?->name ?? 'Unknown',
                    'page' => $page,
                ]);
            }
        }

        return view('livewire.dashboard.seo-issues-panel', [
            'issues' => $issues->take(5),
            'totalCount' => $issues->count(),
        ]);
    }
}
