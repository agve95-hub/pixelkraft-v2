# Pixelkraft — Full QA Bug Report
**Date**: 2026-04-17  
**Auditor**: Automated HTTP-level QA (curl + Python requests + JS bundle analysis)  
**Base URL**: `http://187.124.26.127`  
**Method**: Session-authenticated HTTP testing across all discovered routes, extracted from the compiled Vite/Inertia.js bundle

---

## Correction to Previous Report

> The earlier report stated "all routes return 404." This was because the app runs under the `/dashboard` prefix, not the root. The correct base for all routes is `/dashboard/*` and `/dashboard/sites/{id}/*`. With the corrected prefix, **most routes resolve correctly**.

---

## Route Map (Discovered from JS Bundle)

| Route | HTTP | Component | Status |
|---|---|---|---|
| `/dashboard` | 200 | `dashboard/index` | ✓ |
| `/dashboard/sites` | 200 | `sites/index` | ✓ |
| `/dashboard/sites/create` | 200 | `sites/create` | ✓ |
| `/dashboard/sites/{id}` | 200 | `sites/show` | ✓ |
| `/dashboard/sites/{id}/inbox` | 200 | `sites/inbox` | ✓ |
| `/dashboard/sites/{id}/reports` | 200 | `sites/reports` | ✓ |
| `/dashboard/sites/{id}/campaigns` | 200 | `sites/campaigns` | ✓ |
| `/dashboard/sites/{id}/expenses` | 200 | `sites/expenses` | ✓ |
| `/dashboard/sites/{id}/invoices` | 200 | `sites/invoices` | ✓ |
| `/dashboard/sites/{id}/reminders` | 200 | `sites/reminders` | ✓ |
| `/dashboard/sites/{id}/maintenance` | 200 | `sites/maintenance` | ✓ |
| `/dashboard/sites/{id}/analytics` | **500** | — | ✗ |
| `/dashboard/sites/{id}/files` | 200 | `sites/files` | ✓ |
| `/dashboard/sites/{id}/blog` | 200 | `sites/blog-index` | ✓ |
| `/dashboard/sites/{id}/blog/create` | 200 | `sites/blog-create` | ✓ |
| `/dashboard/sites/{id}/settings` | 200 | `sites/settings` | ✓ |
| `/dashboard/sites/{id}/products` | 200 | `sites/products` | ✓ |
| `/dashboard/sites/{id}/products/create` | 200 | `sites/product-create` | ✓ |
| `/dashboard/sites/{id}/redirects` | 200 | `sites/redirects` | ✓ |
| `/dashboard/sites/{id}/subscribers` | **404** | — | ✗ |
| `/dashboard/sites/{id}/templates` | 200 | `sites/templates` | ✓ |
| `/dashboard/sites/{id}/pages/{pid}/seo` | 200 | `sites/seo-meta` | ✓ |
| `/dashboard/settings` | 200 | `settings/index` | ✓ |
| `/dashboard/system` | 200 | `settings/system` | ✓ |

---

## Bug Reports

---

### [ANALYTICS] — HTTP 500 on Every Site

**Severity**: `critical`

**Steps to reproduce**:
1. Log in → navigate to any site → click Analytics in the sidebar
2. URL: `/dashboard/sites/{any-id}/analytics`

**Expected**: Analytics page loads with visitor charts and metrics.

**Actual**: HTTP 500 Server Error for **all 8 sites**, including both `idle` and `live` sites. The error page contains no stack trace (production mode suppresses details).

**Console errors**: Server returns a generic 500 HTML page with no exception details visible.

**Fix recommendation**: The `AnalyticsController` is throwing an unhandled exception. Most likely cause is a query on a table with no data (division by zero in trend calculation, or a missing DB column). Enable `APP_DEBUG=true` locally to capture the stack trace. Wrap the analytics query in a try/catch and return zero-state data on failure.

---

### [CAMPAIGNS] — POST Creates HTTP 500

**Severity**: `critical`

**Steps to reproduce**:
1. Navigate to `/dashboard/sites/{id}/campaigns`
2. Fill in and submit the New Campaign form

**Expected**: Campaign is saved, list refreshes.

**Actual**: HTTP 500 — server crashes on POST to `/dashboard/sites/{id}/campaigns`. The GET page loads fine.

**Fix recommendation**: The `CampaignController@store` method is throwing. Likely a missing nullable field, a foreign key constraint, or an undefined relationship being eager-loaded on save. Check the Laravel log at `storage/logs/laravel.log`.

---

### [BLOG] — POST Creates HTTP 500

**Severity**: `critical`

**Steps to reproduce**:
1. Navigate to `/dashboard/sites/{id}/blog/create`
2. Fill in title, content, status, submit

**Expected**: Blog post created, redirect to blog index.

**Actual**: POST to `/dashboard/sites/{id}/blog` returns HTTP 500. POST to `/dashboard/sites/{id}/blog/create` returns HTTP 405 (wrong verb on the route).

**Fix recommendation**: The `BlogController@store` method is crashing. Additionally, the create form submits to `POST /blog` (correct) but that route 500s. Check for a missing `site_id` being set, a slug uniqueness collision, or a missing DB column.

---

### [DEPLOY] — Deploy Button Route Does Not Exist

**Severity**: `critical`

**Steps to reproduce**:
1. Open any site's detail page (`/dashboard/sites/{id}`)
2. Click the Deploy button

**Expected**: A deployment is triggered, status changes to "Deploying."

**Actual**: 
- The `sites/show` component calls `POST /dashboard/sites/{id}/deploy`
- This route returns **HTTP 404** — it is not registered
- The `sites/index` component also references `POST /api/v1/sites/{id}/deploy`
- That API endpoint returns **HTTP 401 Unauthenticated** even when called from an authenticated browser session (API routes require token auth, not session auth)

**Fix recommendation**: Register `Route::post('/dashboard/sites/{site}/deploy', [DeployController::class, 'deploy'])` in `routes/web.php`. The API route in `sites/index` should either be replaced with the web route or a Sanctum token must be issued and passed in the request header.

---

### [INVOICES] — POST Route Not Registered (405)

**Severity**: `high`

**Steps to reproduce**:
1. Navigate to `/dashboard/sites/{id}/invoices`
2. Attempt to create a new invoice

**Expected**: Invoice is saved.

**Actual**: POST to `/dashboard/sites/{id}/invoices` returns HTTP 405 Method Not Allowed. The `Allow` header confirms only `GET, HEAD` are registered.

**Fix recommendation**: Register `Route::post('/dashboard/sites/{site}/invoices', [InvoiceController::class, 'store'])` in the web routes. The `InvoiceController` likely exists but the route is missing.

---

### [INBOX] — POST Route Not Registered (405)

**Severity**: `high`

**Steps to reproduce**:
1. Navigate to `/dashboard/sites/{id}/inbox`
2. Attempt to compose or send a message

**Expected**: Message is saved/sent.

**Actual**: POST to `/dashboard/sites/{id}/inbox` returns HTTP 405. The `messages` prop is also missing entirely from the page — the controller only passes `site`, not the inbox data.

**Fix recommendation**: Two issues: (1) Register the POST store route. (2) In `InboxController@index`, add the messages/threads query and pass them as a prop: `'messages' => $site->messages()->latest()->get()`.

---

### [PRODUCTS] — PUT (Edit) Returns 405, DELETE Returns 500

**Severity**: `high`

**Steps to reproduce**:
1. Create a product via `/dashboard/sites/{id}/products`
2. Try to edit it → `PUT /dashboard/sites/{id}/products/{pid}` → **405**
3. Try to delete it → `DELETE /dashboard/sites/{id}/products/{pid}` → **500**

**Expected**: Edit saves changes; delete removes the product.

**Actual**: PUT is not registered (405). DELETE crashes the server (500) — the product remains in the database.

**Fix recommendation**: Register `Route::put(...)` for the update action. Debug the DELETE 500 — likely a missing cascade on a foreign key or an `onDelete()` constraint failing.

---

### [MAINTENANCE] — Preview Route Returns 405 on POST

**Severity**: `high`

**Steps to reproduce**:
1. Navigate to `/dashboard/sites/{id}/maintenance`
2. Click "Preview" button

**Expected**: A preview of the maintenance page is shown.

**Actual**: POST to `/dashboard/sites/{id}/maintenance/preview` returns HTTP 405. The route is only registered as GET.

**Fix recommendation**: The preview likely needs to render a view with the current (unsaved) maintenance settings passed from the form. Register `Route::post('.../maintenance/preview', ...)` or change the frontend to pass state via query params to a GET request.

---

### [EXPENSES] — Data Never Appears in List After Creation

**Severity**: `high`

**Steps to reproduce**:
1. Navigate to `/dashboard/sites/{id}/expenses`
2. Submit the Add Expense form (any valid data)
3. Observe the expenses list — remains empty
4. Refresh the page — still empty

**Expected**: New expense appears in the list.

**Actual**: POST returns HTTP 200 and redirects to `/dashboard` (wrong destination — see redirect bug below). The `expenses` array in the Inertia props is always `[]` regardless of how many times data is submitted. The data is likely not being written to the database, or is being written to the wrong `site_id`.

**Additional**: Validation is absent on the server — posting with empty `amount`, `description`, or a non-numeric amount all return HTTP 200.

**Fix recommendation**: Check `ExpenseController@store` — confirm it's attaching the correct `site_id`. Add `$request->validate([...])` for required fields and numeric amount. Fix the redirect to return to `route('sites.expenses.index', $site)`.

---

### [REPORTS] — POST Redirects to `/templates`, Data Not Saved

**Severity**: `high`

**Steps to reproduce**:
1. Navigate to `/dashboard/sites/{id}/reports`
2. Submit the New Report form
3. Observe redirect URL and reports list

**Expected**: Report is saved, redirect to reports list.

**Actual**: POST to `/dashboard/sites/{id}/reports` returns HTTP 200 and redirects to `/dashboard/sites/{id}/templates` — a completely unrelated section. Reports list remains empty after submission.

**Console errors**: No errors; silent failure followed by wrong redirect.

**Fix recommendation**: In `ReportController@store`, the `return redirect()->route(...)` call is pointing to the wrong named route. Fix to `route('sites.reports.index', $site)`. Also debug why the report is not being persisted.

---

### [REMINDERS] — `due_at` Field Never Saves (Always Null)

**Severity**: `high`

**Steps to reproduce**:
1. Navigate to reminders → Add Reminder with a future date/time → Save
2. Check the reminder in the list

**Expected**: `due_at` displays the chosen date and time.

**Actual**: All reminders have `due_at: null` regardless of what date was submitted. Tested with `2026-04-25 10:00`, `2026-04-30 09:00`, and `2020-01-01 00:00` — all null.

**Fix recommendation**: The controller is likely not mapping `due_at` from the request. Check the `fillable` array on the `Reminder` model — `due_at` may be missing. Also check the field name: the frontend may send `due_at` while the column is named `reminder_at` or similar.

---

### [REMINDERS] — `complete` Action Does Not Set `completed_at`

**Severity**: `high`

**Steps to reproduce**:
1. Create a reminder → POST to `/reminders/{id}/complete`
2. Refresh the reminders list

**Expected**: `completed_at` is set to the current timestamp; reminder is visually marked done.

**Actual**: Returns HTTP 200 and redirects correctly, but `completed_at` remains `null`. The reminder appears unchanged.

**Fix recommendation**: In `ReminderController@complete` (or equivalent), the update call is likely not persisting: `$reminder->update(['completed_at' => now()])`. Check that the method is actually calling `->save()` or `->update()`.

---

### [SITE CREATE] — Form Submits But No Site Is Created

**Severity**: `high`

**Steps to reproduce**:
1. Navigate to `/dashboard/sites/create`
2. Fill in name, domain, client info → Submit
3. Check `/dashboard/sites` for the new entry

**Expected**: New site appears in the sites list.

**Actual**: POST to `/dashboard/sites` returns HTTP 200 and redirects back to `/dashboard/sites/create` (the create page itself). The sites list count remains 8. No site is created.

**Additional**: Empty name and XSS payloads also return 200 with no error — validation is completely absent.

**Fix recommendation**: Check `SiteController@store` — it appears to be failing validation silently and re-rendering the create page without error messages. The Inertia `$errors` bag should be populated and returned. Add `$request->validate(['name' => 'required|string|max:255', ...])`.

---

### [SETTINGS] — `github_repo` Field Not Saved (Field Name Mismatch)

**Severity**: `medium`

**Steps to reproduce**:
1. Navigate to `/dashboard/sites/{id}/settings`
2. Edit the GitHub repository URL field
3. Save → reload settings

**Expected**: GitHub repo URL is persisted.

**Actual**: The `github_repo` value is `null` after save. The database column is named `repo_url` (confirmed from the `site` props: `"repo_url": "https://github.com/..."`) but the frontend form sends the field as `github_repo`. The controller likely ignores the mismatched field name.

**Fix recommendation**: Either rename the form field in the Vue component to `repo_url`, or add a mapping in the controller: `$site->repo_url = $request->github_repo`.

---

### [SEO META] — `title` Field Does Not Save

**Severity**: `medium`

**Steps to reproduce**:
1. Open any page's SEO settings: `/dashboard/sites/{id}/pages/{page_id}/seo`
2. Set a title and meta description → Save (PUT)
3. Reload the SEO page

**Expected**: Both `title` and `meta_description` are saved.

**Actual**: `meta_description` saves correctly. `title` remains `null`. The page `title` field may be mapped to a different column name or excluded from `$fillable`.

**Fix recommendation**: Check the `Page` model's `$fillable` array. The column may be `page_title` or `seo_title` instead of `title`. Check the SEO controller's update call.

---

### [INBOX / FILES / MAINTENANCE / TEMPLATES / SYSTEM] — Props Missing from Controller

**Severity**: `high`

**Steps to reproduce**: Navigate to any of these sections and inspect the Inertia page data.

**Expected**: Controllers pass relevant data arrays (messages, files, maintenance config, templates, system info) as Inertia props.

**Actual**: All of these controllers pass only `['site' => $site]` — no section-specific data at all:
- **Inbox**: No `messages` prop → component renders with no data to display
- **Files**: No `files` prop → component shows "File manager coming soon." (hardcoded placeholder)
- **Templates**: No `templates` prop → list cannot render
- **System**: No props at all → system info panel is empty

**Fix recommendation**:
- `InboxController@index`: add `'messages' => $site->messages()->with('sender')->latest()->get()`
- `FilesController@index`: this component is a confirmed stub — file management hasn't been implemented
- `TemplatesController@index`: add `'templates' => $site->templates()->get()`
- `SystemController@index`: add PHP version, disk usage, queue status, last deploy time

---

### [GLOBAL REDIRECTS] — 5 Controllers Redirect to `/dashboard` Instead of Section

**Severity**: `medium`

After a successful POST, these controllers redirect to `/dashboard` (the main dashboard) instead of back to their section:

| Section | Actual Redirect | Should Be |
|---|---|---|
| Expenses store | `/dashboard` | `/dashboard/sites/{id}/expenses` |
| Reminders store | `/dashboard` | `/dashboard/sites/{id}/reminders` |
| Blog store | `/dashboard` | `/dashboard/sites/{id}/blog` |
| Maintenance update | `/dashboard` | `/dashboard/sites/{id}/maintenance` |
| Reports store | `/dashboard/sites/{id}/templates` | `/dashboard/sites/{id}/reports` |

**Fix recommendation**: In each controller's `store`/`update` method, replace `return redirect()->route('dashboard')` with the correct named route, e.g. `return redirect()->route('sites.expenses.index', $site)`.

---

### [SETTINGS] — `/user/profile-information` and `/user/password` Redirect to Wrong Page

**Severity**: `medium`

**Steps to reproduce**:
1. Go to `/dashboard/settings` (global settings / profile)
2. Try to update name or change password

**Expected**: Profile updates are saved; redirect stays on settings page.

**Actual**: Both `PUT /user/profile-information` and `PUT /user/password` return HTTP 200 but redirect to `/dashboard/sites/{id}/maintenance/preview` — a completely unrelated route from a previous request in the same session. This is almost certainly a Fortify middleware conflict where the `redirectsTo` config is picking up a stale `intended` URL.

**Fix recommendation**: In `config/fortify.php`, set explicit redirect targets: `'home' => '/dashboard'`. Clear `session()->forget('url.intended')` after login.

---

### [SUBSCRIBERS] — Route Returns 404

**Severity**: `medium`

**Steps to reproduce**: Navigate to `/dashboard/sites/{id}/subscribers`

**Expected**: Subscribers list page.

**Actual**: HTTP 404 — route is not registered. The JS bundle includes a `subscribers` component, confirming the feature was planned.

**Fix recommendation**: Register the subscribers routes in `routes/web.php` and implement `SubscriberController`.

---

### [MAINTENANCE] — Settings Stored as String "1" Instead of Boolean

**Severity**: `low`

**Steps to reproduce**: Enable maintenance mode, save, then inspect `maintenance_settings` from the site props.

**Expected**: `{ "enabled": true, ... }`

**Actual**: `{ "enabled": "1", ... }` — PHP form inputs are strings; the controller is not casting `enabled` to boolean before storing in the JSON column.

**Fix recommendation**: In the controller: `'enabled' => $request->boolean('enabled')`.

---

### [DASHBOARD] — Traffic Chart Is Permanently Flat (All Zeros)

**Severity**: `high`

**Steps to reproduce**: Log in → view dashboard → observe the traffic/visitors chart.

**Expected**: Chart shows visitor trend over the last 30 days.

**Actual**: `trafficVisitors: 0`, all 30 data points are `visitors: 0`. The SVG path data is a flat horizontal line at the bottom of the chart. `uptimePercent: 0` for all sites. The data collectors (traffic analytics agent, uptime ping) are either not running or not writing to the database.

**Fix recommendation**: Check `php artisan schedule:list` — confirm that the traffic ingestion and uptime check commands are registered. Check `php artisan queue:work` is running. Verify cron is configured on the server (`* * * * * php /path/to/artisan schedule:run`).

---

### [SITE SHOW] — Uptime / Response Time All Null

**Severity**: `high`

Site detail page (`sites/show`) shows: `uptimePercent: null`, `latestResponseMs: null`, `p95ResponseMs: null`, `visitorsTrendPercent: null`. The uptime monitor has no data for any site — not even sites marked as `deploy_status: live`.

**Fix recommendation**: Same as above — the background monitoring job is not running. Check that `UptimeCheckJob` or equivalent is dispatched on a schedule and that the queue worker is processing it.

---

### [LOGIN] — No Server-Side Error Message on Wrong Password

**Severity**: `low`

**Steps to reproduce**: Submit the login form with wrong credentials.

**Expected**: Clear message like "These credentials do not match our records."

**Actual**: Redirects back to login page (HTTP 200), but the error message area appears empty in the raw HTML. The Flux UI `data-flux-error` div is present but empty. The error is returned via Inertia's `$errors` bag and requires the frontend JS to render it — so it may display correctly in a real browser but the error is not visible in the raw HTML source.

**Note**: This is likely working correctly in the browser since Inertia's error bag is reactive. Mark as low/cosmetic pending browser verification.

---

### [PRODUCTS] — Validation Absent on Create

**Severity**: `medium`

**Steps to reproduce**: Submit the product create form with no name, no price, or a negative price.

**Expected**: Validation errors returned.

**Actual**: HTTP 200 — the product is accepted regardless. No server-side validation on `name` (required), `price` (numeric, positive), or `currency`.

**Fix recommendation**: Add `$request->validate(['name' => 'required|string', 'price' => 'required|numeric|min:0', 'currency' => 'required|in:CHF,EUR,USD'])` in `ProductController@store`.

---

### [GLOBAL] — API Routes Require Token Auth, Not Session Auth

**Severity**: `medium`

**Steps to reproduce**: The `sites/index` component calls `POST /api/v1/sites/{id}/deploy`. This requires a bearer token — cookie session auth is rejected with 401.

**Actual**: No mechanism exists in the frontend to obtain or pass an API token. The API is unreachable from the browser interface.

**Fix recommendation**: Either (a) move the deploy trigger to a web route (`/dashboard/sites/{id}/deploy`) authenticated by session, or (b) issue a Sanctum token on login and store it for API calls. Option (a) is simpler.

---

### [FILES] — Component Is a Confirmed Stub

**Severity**: `medium`

The `files.js` component source contains only: `"File manager coming soon."` — hardcoded in the component body. No upload logic, no file listing, no API calls. The controller also passes zero props.

**Fix recommendation**: Implement R2/S3 file listing and upload. Wire the controller to pass `'files' => $files` and implement the upload endpoint.

---

### [CROSS-SITE] — Data Isolation Confirmed Working

**Severity**: N/A (no bug)

Attempting to delete a reminder belonging to Site A using Site B's URL returns HTTP 404. Cross-site resource access is correctly blocked at the controller level.

---

## Final Summary

### Bug Count by Severity

| Severity | Count |
|---|---|
| `critical` | 4 |
| `high` | 13 |
| `medium` | 8 |
| `low` | 2 |
| **Total** | **27** |

---

### Top 5 Most Impactful Bugs (Fix First)

1. **Analytics 500** — entire analytics section is dead for every site
2. **Deploy button 404** — the core CMS action doesn't work
3. **Inbox / Files / Maintenance missing props** — 3 sections render with no data at all
4. **Campaign + Blog POST 500** — two content creation flows crash the server
5. **Expense / Report / Reminder data not saving + wrong redirects** — 3 core management sections are broken in the same way (wrong redirect + data not persisting)

---

### Features That Work Correctly

- Login and logout (session properly cleared)
- Auth guard (unauthenticated access redirects to login)
- Case-insensitive email on login
- Cross-site data isolation (correct 404 on wrong-site resource access)
- Dashboard stat cards (SEO count, site count, page count load correctly)
- Sites index and site detail page (show)
- Site settings page (partially — most fields save, `github_repo` mismatch)
- Reminders: create, list, delete (due_at and complete are broken)
- Products: create and list (edit and delete are broken)
- Redirects: create, toggle, delete (fully working)
- SEO meta page: `meta_description` saves (title does not)
- Blog edit and update pages load

---

### Recommended Fix Order

**Phase 1 — Unblock server errors (1–2 hours)**
1. Fix `AnalyticsController` 500 (add try/catch, return zero-state on failure)
2. Fix `CampaignController@store` 500
3. Fix `BlogController@store` 500 (and register the correct POST route)
4. Fix `ProductController@destroy` 500

**Phase 2 — Register missing routes (30 min)**
5. Register `POST /dashboard/sites/{site}/deploy` (web route)
6. Register `POST /dashboard/sites/{site}/invoices`
7. Register `POST /dashboard/sites/{site}/inbox`
8. Register `POST /dashboard/sites/{site}/maintenance/preview`
9. Register `PUT /dashboard/sites/{site}/products/{product}`
10. Register `/dashboard/sites/{site}/subscribers` routes

**Phase 3 — Fix data not saving (2–3 hours)**
11. Fix `ExpenseController@store` — ensure site_id is set, add validation
12. Fix `ReportController@store` — fix redirect route name
13. Fix `ReminderController@store` — map `due_at` field correctly
14. Fix `ReminderController@complete` — ensure `update(['completed_at' => now()])` persists
15. Fix `SiteController@store` — add validation, fix redirect back on failure
16. Fix `github_repo` → `repo_url` field name mismatch in settings
17. Fix SEO `title` field not saving

**Phase 4 — Fix redirects (1 hour)**
18. Fix all 5 controllers redirecting to `/dashboard` instead of their section
19. Fix `/user/profile-information` and `/user/password` redirect targets (Fortify config)

**Phase 5 — Fill missing props (1–2 hours)**
20. `InboxController@index` — add messages query
21. `TemplatesController@index` — add templates query
22. `SystemController@index` — add system info

**Phase 6 — Background jobs (time varies)**
23. Confirm cron + queue worker are running
24. Fix traffic data collection and uptime monitoring
