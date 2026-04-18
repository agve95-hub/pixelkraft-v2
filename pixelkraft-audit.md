# Pixelkraft v2 — Live App Audit
**Date:** 2026-04-17  
**App:** http://187.124.26.127  
**Repo:** https://github.com/agve95-hub/pixelkraft-v2

---

## Route Status

| Route | Status | Notes |
|---|---|---|
| `/dashboard` | ✅ 200 | OK |
| `/dashboard/sites` | ✅ 200 | OK |
| `/dashboard/sites/{id}` | ✅ 200 | Site overview |
| `/dashboard/sites/{id}/files` | ✅ 200 | OK |
| `/dashboard/sites/{id}/inbox` | ✅ 200 | OK |
| `/dashboard/sites/{id}/blog` | ✅ 200 | OK |
| `/dashboard/sites/{id}/redirects` | ✅ 200 | OK |
| `/dashboard/sites/{id}/expenses` | ✅ 200 | OK |
| `/dashboard/sites/{id}/reports` | ✅ 200 | OK |
| `/dashboard/sites/{id}/reminders` | ✅ 200 | OK |
| `/dashboard/sites/{id}/settings` | ✅ 200 | OK |
| `/dashboard/sites/{id}/invoices` | ✅ 200 | OK |
| `/dashboard/sites/{id}/campaigns` | ✅ 200 | OK |
| `/dashboard/sites/{id}/maintenance` | ✅ 200 | OK |
| `/dashboard/sites/{id}/templates` | ✅ 200 | OK |
| `/dashboard/sites/{id}/pages/{pageId}/edit` | ✅ 200 | Visual editor loads fully |
| `/dashboard/sites/{id}/pages/{pageId}/seo` | ✅ 200 | SEO editor loads fully |
| `/dashboard/sites/{id}/pages` | ❌ 404 | Route missing — see Bug #1 |
| `/dashboard/sites/{id}/analytics` | ❌ 500 | Server error — see Bug #2 |

---

## Bug #1 — Pages listing unreachable (critical)

**Symptom:** `/sites/{site}/pages` returns 404.

**Root cause:** Three separate failures stacked on top of each other:

1. The route `GET /sites/{site}/pages` does not exist in `routes/web.php`
2. The `PageListing` Livewire component (`app/Livewire/Sites/PageListing.php`) and its view (`resources/views/livewire/sites/page-listing.blade.php`) are fully built but never rendered anywhere — orphaned code
3. The "All pages" link on the site overview (`show.tsx` line 143) points back to `/dashboard/sites/${site.id}` — the same page it's already on — instead of `/dashboard/sites/${site.id}/pages`

**Impact:** The visual editor and SEO editor both work fine at their direct URLs, but there is no UI path to reach them. Users cannot navigate to any page's Edit or SEO buttons.

**Fix — 3 files:**

`routes/web.php` — add after the files route:
```php
Route::get('/sites/{site}/pages', fn (Site $site) => view('dashboard.sites.pages', ['site' => $site]))->name('sites.pages');
```

`resources/views/dashboard/sites/pages.blade.php` — create new file:
```blade
<x-layouts.app>
    <x-slot:title>Pages — {{ $site->name }}</x-slot:title>
    <div>
        <div class="mb-6">
            <a href="{{ route('sites.show', $site) }}" class="text-xs text-zinc-500 hover:text-violet-400 transition">← {{ $site->name }}</a>
            <h2 class="text-lg font-semibold text-zinc-100 mt-1">Pages</h2>
            <p class="text-sm text-zinc-500">Manage, edit, and configure SEO for all pages on this site.</p>
        </div>
        @livewire('sites.page-listing', ['siteId' => $site->id])
    </div>
</x-layouts.app>
```

`resources/js/pages/sites/show.tsx` line 143 — fix the link:
```tsx
// Before
href={`/dashboard/sites/${site.id}`}

// After
href={`/dashboard/sites/${site.id}/pages`}
```

---

## Bug #2 — Analytics 500 error

**Symptom:** `/sites/{site}/analytics` returns HTTP 500.

**Status:** Not yet investigated at the source level. Likely a missing DB column, a failed query in `SiteAnalyticsController`, or a missing `ga_property_id` / `cf_zone_id` causing a null reference. Needs a look at Laravel logs on the server.

**Quick check:**
```bash
tail -50 /var/www/pixelkraft/storage/logs/laravel.log
```

---

## What's already working (no action needed)

- Visual editor (`visual-editor.blade.php`) — fully functional: iframe canvas, region overlays, inline editing, Layers/Pages/Media tabs, right inspector with Properties/SEO/History/Code tabs, undo/redo, Save draft, Publish
- SEO editor (`seo/meta.blade.php`) — meta title, description, OG fields, canonical, robots meta, schema, redirects, robots.txt all present
- `PageListing` Livewire component — search, sort by title/URL/SEO score/updated_at, pagination, Edit + SEO buttons per row — just needs the route and view above to become reachable

---

## Summary

| # | Issue | Severity | Fix size |
|---|---|---|---|
| 1 | Pages listing 404 → editor/SEO unreachable | Critical | 3 files, ~15 lines |
| 2 | Analytics 500 | Medium | Needs log investigation |
| 3 | "All pages" link self-references | Low (part of #1) | 1 line |
