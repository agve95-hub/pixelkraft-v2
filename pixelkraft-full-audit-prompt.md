# Pixelkraft — Full Production Audit, Fix & QA Prompt

You are an autonomous agent performing a complete production fix and hands-on QA pass on a live Laravel/Livewire application. You will: SSH into the server, pull the latest code from GitHub, apply all fixes, then **manually test every page, every button, every form field, and every interactive element** — filling in real data and verifying real outcomes.

Work through every section in order. Do not skip steps. Report pass/fail for every test case.

---

## Access Details

| Key | Value |
|-----|-------|
| Server | `srv1511172.hstgr.cloud` / `187.124.26.127` |
| OS | AlmaLinux 10 |
| SSH user | `root` |
| SSH password | `@F4W9RkO0vb.s7H9z;0V` |
| App path | `/var/www/pixelkraft` |
| Live URL | `http://187.124.26.127` |
| Admin email | `admin@pixelkraft.com` |
| Admin password | `admin123` |

---

## Phase 0 — Server Health Check

SSH in and verify the environment before touching anything.

```bash
ssh root@187.124.26.127
cd /var/www/pixelkraft

# Verify services
systemctl status nginx --no-pager
systemctl status php-fpm --no-pager
systemctl status mysql --no-pager

# Verify PHP version
php --version

# Verify Laravel is alive
php artisan --version
php artisan env

# Check current git status
git status
git log --oneline -5
git remote -v

# Check disk space
df -h /var/www

# Check error log tail
tail -50 storage/logs/laravel.log
```

Report every output. If nginx, php-fpm, or mysql is not running, start it before proceeding:
```bash
systemctl start nginx
systemctl start php-fpm
systemctl start mysql
```

---

## Phase 1 — Pull Latest Code from GitHub

Pull the latest production branch and run all deployment steps.

```bash
cd /var/www/pixelkraft

# Stash any local changes before pulling
git stash

# Pull latest from main (or production branch — confirm branch name first)
git fetch origin
git branch -a
git pull origin main   # replace 'main' with actual branch if different

# If there is a specific page that needs migrating (e.g. a new blade view, controller, or Livewire component added on GitHub but not yet on server), confirm it arrived:
git diff HEAD~1 HEAD --name-only

# Install any new composer dependencies
composer install --no-dev --optimize-autoloader

# Install any new npm dependencies and rebuild assets
npm ci
npm run build

# Run migrations (READ EACH MIGRATION FIRST before running)
php artisan migrate:status
php artisan migrate --pretend
# Only run the real migration after confirming --pretend output looks safe:
php artisan migrate --force

# Clear all caches
php artisan optimize:clear
php artisan view:clear
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Fix permissions
chown -R nginx:nginx /var/www/pixelkraft/storage
chown -R nginx:nginx /var/www/pixelkraft/bootstrap/cache
chmod -R 775 /var/www/pixelkraft/storage
chmod -R 775 /var/www/pixelkraft/bootstrap/cache
```

Confirm the pull was successful. List the files that changed:
```bash
git log --oneline -3
git show --stat HEAD
```

---

## Phase 2 — Apply All Code Fixes

Apply each fix in order. After every fix, run `php artisan optimize:clear` and verify the affected route still returns HTTP 200 before moving to the next.

---

### Fix 1 — Livewire POST / Auth / Visibility Failure

**Files:**
```
app/Http/Middleware/SiteAccess.php          — line 9
app/Models/Site.php                         — line 546
```

**Steps:**

1. Read `SiteAccess.php` fully. The middleware on line 9 aborts Livewire POST requests because it checks a condition (session token, route parameter, or site visibility) that doesn't exist in the Livewire update context. Fix the condition to allow Livewire POST requests through:

```php
// Example pattern — adapt to actual code:
if ($request->hasHeader('X-Livewire') || $request->routeIs('livewire.*')) {
    return $next($request);
}
```

2. Read `Site.php` line 546. If the visibility gate queries the DB per-request using session or request data that isn't available in Livewire POST context, cache the result per site ID or pass site ID from the component's mount state instead.

3. Verify all six affected Livewire components import and use `SiteAccess` correctly:
```
app/Livewire/SiteInbox.php       — line 31
app/Livewire/ReportManager.php   — line 29
app/Livewire/ExpenseManager.php  — line 31
app/Livewire/ReminderManager.php — line 28
app/Livewire/InvoiceManager.php  — line 144
app/Livewire/SiteSettings.php    — line 96
```

4. After fixing, run:
```bash
php artisan optimize:clear
curl -X POST http://187.124.26.127/livewire/update \
  -H "Content-Type: application/json" \
  -H "X-Livewire: true" \
  -d '{}' \
  -w "\nHTTP %{http_code}\n"
```
Expect HTTP 200 or 422, not 404.

---

### Fix 2 — FileManager Missing Import

**File:** `app/Livewire/FileManager.php`

Add the missing use statement at the top of the file:
```php
use App\Models\Site;
```

Confirm it doesn't conflict with any other `Site` alias in that namespace.

```bash
php artisan optimize:clear
curl -o /dev/null -s -w "%{http_code}" http://187.124.26.127/dashboard/sites/1/files
# Expect 200
```

---

### Fix 3 — Analytics Schema Guards

**Files:**
```
app/Http/Controllers/SiteAnalyticsController.php   — lines 82, 83, 85
app/Services/AnalyticsAggregator.php               — line 64
```

Wrap every query that hits release/event tables with a schema guard:

```php
use Illuminate\Support\Facades\Schema;

// Before querying:
if (!Schema::hasTable('releases')) {
    return collect();
}
// Then run the query
```

Apply the same pattern to `AnalyticsAggregator.php` line 64.

```bash
php artisan optimize:clear
curl -o /dev/null -s -w "%{http_code}" http://187.124.26.127/dashboard/sites/1/analytics
# Expect 200 (with empty state UI, not 500)
```

---

### Fix 4 — Editor Empty Code Buffer

**Files:**
```
app/Livewire/VisualEditor.php                     — lines 83, 603
resources/views/livewire/visual-editor.blade.php  — line 405
```

On `VisualEditor.php` line 603, replace the silent empty fallback:
```php
// Replace:
$this->codeContent = '';

// With:
$this->codeContent = '';
$this->editorError = 'Source file could not be loaded: ' . ($resolvedPath ?? 'path unknown');
```

In `visual-editor.blade.php` line 405, add error display before the code pane:
```html
@if($editorError)
  <div class="editor-error-banner">
    {{ $editorError }}
  </div>
@endif
```

---

### Fix 5 — Mojibake / Text Encoding Corruption

Run this grep to find ALL corrupted characters across the project:
```bash
grep -rn --include="*.php" --include="*.blade.php" \
  "â€\|Ã©\|Â£\|â€™\|â€"\|Ã\|â€œ\|â€\|Â©\|Â®" \
  /var/www/pixelkraft/resources/views/ \
  /var/www/pixelkraft/app/Models/ \
  /var/www/pixelkraft/app/Livewire/
```

Fix every match. Common substitutions:
| Corrupted | Correct |
|-----------|---------|
| `â€™` | `'` |
| `â€œ` | `"` |
| `â€` | `"` |
| `â€"` | `—` |
| `â€"` | `–` |
| `Â£` | `£` |
| `Â©` | `©` |
| `Ã©` | `é` |

Priority files from audit:
```
resources/views/livewire/site-inbox.blade.php      — line 9
resources/views/livewire/invoice-manager.blade.php — line 21
resources/views/layouts/app.blade.php              — line 245
app/Models/Invoice.php                             — line 112
```

Save every edited file as UTF-8 without BOM. Confirm with:
```bash
file -i resources/views/layouts/app.blade.php
# Should output: charset=utf-8
```

---

### Fix 6 — Idempotent Migration

**File:** `database/migrations/2026_04_10_120001_add_is_degraded_to_uptime_checks_table.php`

Replace the `up()` and `down()` methods with idempotent versions:

```php
public function up()
{
    if (!Schema::hasColumn('uptime_checks', 'is_degraded')) {
        Schema::table('uptime_checks', function (Blueprint $table) {
            $table->boolean('is_degraded')->default(false)->after('checked_at');
        });
    }
}

public function down()
{
    if (Schema::hasColumn('uptime_checks', 'is_degraded')) {
        Schema::table('uptime_checks', function (Blueprint $table) {
            $table->dropColumn('is_degraded');
        });
    }
}
```

Verify:
```bash
php artisan migrate:status | grep is_degraded
php artisan migrate --pretend 2>&1 | tail -10
```

---

### Fix 7 — Stub Controls

**File:** `resources/views/livewire/visual-editor.blade.php` — lines 64, 67, 88
**File:** `resources/views/livewire/analytics.blade.php` — lines 21, 201

For every stub button/control, add disabled state and tooltip:
```html
<!-- Example pattern -->
<button disabled title="Coming soon" style="opacity: 0.4; cursor: not-allowed; pointer-events: none;">
  Undo
</button>
```

---

## Phase 3 — Full QA: Every Page, Every Button, Every Form

Use `curl` for HTTP status checks and browser automation (via Playwright or Puppeteer if available) for interactive testing. If no browser automation is available, perform all tests manually via the live URL and document results.

First, establish an authenticated session for all curl tests:

```bash
# Login and capture session cookie
curl -c /tmp/pk_cookies.txt -b /tmp/pk_cookies.txt \
  -X POST http://187.124.26.127/login \
  -d "email=admin@pixelkraft.com&password=admin123&_token=CSRF_TOKEN_HERE" \
  -L -w "\nHTTP %{http_code}\n"
```

To get the CSRF token first:
```bash
CSRF=$(curl -s http://187.124.26.127/login | grep 'csrf-token' | sed 's/.*content="\([^"]*\)".*/\1/')
echo "CSRF: $CSRF"
```

---

### Page 1 — Login (`/login`)

**Form fields to test:**

| Field | Test value | Expected result |
|-------|-----------|----------------|
| Email (empty) | _(blank)_ | Validation error: required |
| Password (empty) | _(blank)_ | Validation error: required |
| Email (invalid format) | `notanemail` | Validation error: invalid email |
| Email (wrong credentials) | `wrong@test.com` / `wrongpass` | Error: credentials don't match |
| Email + Password (correct) | `admin@pixelkraft.com` / `admin123` | Redirect to dashboard |

**Buttons to test:**

| Button | Action | Expected |
|--------|--------|----------|
| Sign in | Submit form | Redirect to `/dashboard` on success, error message on failure |
| Forgot password link | Click | Navigate to password reset page |

```bash
# Test wrong credentials
curl -c /tmp/pk_cookies.txt -b /tmp/pk_cookies.txt \
  -X POST http://187.124.26.127/login \
  -d "email=wrong@test.com&password=wrongpass&_token=$CSRF" \
  -L -w "\nHTTP %{http_code}\n"

# Test correct credentials
curl -c /tmp/pk_cookies.txt -b /tmp/pk_cookies.txt \
  -X POST http://187.124.26.127/login \
  -d "email=admin@pixelkraft.com&password=admin123&_token=$CSRF" \
  -L -w "\nHTTP %{http_code}\n"
```

**PASS criteria:** Correct login redirects to dashboard (HTTP 302 → 200). Wrong credentials stay on login with error.

---

### Page 2 — Dashboard (`/dashboard`)

**Status checks:**
```bash
curl -b /tmp/pk_cookies.txt -o /dev/null -s -w "%{http_code}" http://187.124.26.127/dashboard
# Expect 200
```

**Buttons / controls to test:**

| Element | Location | Action | Expected |
|---------|----------|--------|----------|
| Site cards | Dashboard grid | Click a site card | Navigate to that site's overview |
| "New site" / "Add site" button | Top right or sidebar | Click | Opens create-site modal or page |
| Notification bell | Nav | Click | Shows notification dropdown |
| User avatar / menu | Top right | Click | Shows dropdown with Profile, Logout |
| Logout button | User dropdown | Click | Logs out, redirects to `/login` |
| Sidebar nav links | Left sidebar | Click each | Navigate to correct page, active state highlights |

**Sidebar links to click and verify (HTTP 200 each):**
```bash
for path in /dashboard /dashboard/sites /dashboard/inbox /dashboard/reports \
            /dashboard/expenses /dashboard/reminders /dashboard/invoices \
            /dashboard/analytics /dashboard/settings; do
  STATUS=$(curl -b /tmp/pk_cookies.txt -o /dev/null -s -w "%{http_code}" http://187.124.26.127$path)
  echo "$path — $STATUS"
done
```

**PASS criteria:** All sidebar links return 200. No redirect to login (which would mean session is broken).

---

### Page 3 — Create New Site

Navigate to the new site creation page or open the modal.

**Form fields to fill:**

| Field | Test value |
|-------|-----------|
| Site name | `Test Site QA` |
| Domain / URL | `https://testsite-qa.com` |
| Client name | `QA Client` |
| Description | `This is a QA test site created during audit` |
| Any color/tag selectors | Select the first available option |

**Buttons:**

| Button | Expected |
|--------|----------|
| Create / Save | Site appears in dashboard list |
| Cancel / Close modal | Modal closes, no site created |

**Verify:** After creating, the site appears in the dashboard grid. Note the site ID from the URL for use in subsequent tests (referred to as `{SITE_ID}` below).

---

### Page 4 — Site Overview (`/dashboard/sites/{SITE_ID}`)

```bash
curl -b /tmp/pk_cookies.txt -o /dev/null -s -w "%{http_code}" \
  http://187.124.26.127/dashboard/sites/{SITE_ID}
# Expect 200
```

**Buttons to test:**

| Button | Expected |
|--------|----------|
| Edit site / Settings | Navigate to settings page |
| Any quick-action buttons | Fire without 500 |
| Sub-nav tabs (Inbox, Reports, Expenses, etc.) | Each tab loads correctly |

---

### Page 5 — Site Inbox (`/dashboard/sites/{SITE_ID}/inbox`)

```bash
curl -b /tmp/pk_cookies.txt -o /dev/null -s -w "%{http_code}" \
  http://187.124.26.127/dashboard/sites/{SITE_ID}/inbox
# Expect 200
```

**Compose form — fill every field:**

| Field | Test value |
|-------|-----------|
| To / Recipient | `client@testsite-qa.com` |
| Subject | `Test message from QA audit` |
| Message body | `Hello, this is a test message sent during the Pixelkraft production audit. Please ignore.` |
| Any CC field | `cc@testsite-qa.com` |
| Any attachment button | Click, cancel without uploading (verify modal opens) |

**Buttons:**

| Button | Action | Expected |
|--------|--------|----------|
| Compose / New message | Click | Opens compose form |
| Send | Click with form filled | Message saved/sent, appears in inbox list |
| Save draft | Click | Message saved as draft |
| Discard / Cancel | Click | Form closes, no message created |
| Delete message (if messages exist) | Click on a message → delete | Message removed from list |
| Mark as read / unread | Click toggle | Status changes |

**Livewire test:** After clicking Send, the page should update without a full reload. If it full-page reloads or shows a 404 error, the Livewire fix (Fix 1) did not work.

**PASS criteria:** Send button saves the message and shows it in the inbox list. No 404 or 500 errors.

---

### Page 6 — Site Reports (`/dashboard/sites/{SITE_ID}/reports`)

```bash
curl -b /tmp/pk_cookies.txt -o /dev/null -s -w "%{http_code}" \
  http://187.124.26.127/dashboard/sites/{SITE_ID}/reports
# Expect 200
```

**Create new report — fill every field:**

| Field | Test value |
|-------|-----------|
| Report title | `Monthly QA Audit Report` |
| Date | Today's date |
| Status | Select first available option |
| Notes / Description | `This report was created during the QA audit of Pixelkraft. All systems tested.` |
| Any dropdown selectors | Select each option in turn |
| Any tag/label fields | `qa, audit, test` |

**Buttons:**

| Button | Action | Expected |
|--------|--------|----------|
| New report / Create | Click | Opens form |
| Save | Click with form filled | Report appears in list |
| Edit (on existing report) | Click | Form pre-filled with existing data |
| Update / Save changes | Click after editing | Changes reflected in list |
| Delete | Click → confirm dialog | Report removed from list |
| Export / Download (if present) | Click | File downloads or export modal opens |
| Filter / Sort controls | Click each | List re-orders or filters |
| Pagination (if present) | Click next/prev | Page changes |

**PASS criteria:** Report saves and appears in list. Edit and delete work. No Livewire errors.

---

### Page 7 — Site Expenses (`/dashboard/sites/{SITE_ID}/expenses`)

```bash
curl -b /tmp/pk_cookies.txt -o /dev/null -s -w "%{http_code}" \
  http://187.124.26.127/dashboard/sites/{SITE_ID}/expenses
# Expect 200
```

**Create new expense — fill every field:**

| Field | Test value |
|-------|-----------|
| Description | `Server hosting - QA test expense` |
| Amount | `149.99` |
| Currency | `CHF` (or first available) |
| Date | Today's date |
| Category | Select first available option |
| Notes | `Added during Pixelkraft production audit` |
| Receipt upload (if present) | Click, cancel without uploading |

**Buttons:**

| Button | Action | Expected |
|--------|--------|----------|
| Add expense / New | Click | Opens form |
| Save | Click with form filled | Expense appears in list with correct amount |
| Edit | Click existing expense | Pre-fills form |
| Save changes | After edit | Updated values show in list |
| Delete | Click → confirm | Expense removed |
| Filter by date range | Set start + end date | List filters |
| Filter by category | Select category | List filters |
| Export (if present) | Click | Export initiated |
| Total / summary card | Observe | Shows correct running total |

**PASS criteria:** Expense saves, amount displays correctly (no encoding corruption with currency symbols like `£` or `CHF`).

---

### Page 8 — Site Reminders (`/dashboard/sites/{SITE_ID}/reminders`)

```bash
curl -b /tmp/pk_cookies.txt -o /dev/null -s -w "%{http_code}" \
  http://187.124.26.127/dashboard/sites/{SITE_ID}/reminders
# Expect 200
```

**Create new reminder — fill every field:**

| Field | Test value |
|-------|-----------|
| Title | `QA Audit Follow-up` |
| Description | `Follow up on all issues found during the April 2026 production audit` |
| Due date | Tomorrow's date |
| Priority | High (or first available) |
| Repeat / Recurrence | Once (or none) |
| Assigned to | Select self (admin) |

**Buttons:**

| Button | Action | Expected |
|--------|--------|----------|
| New reminder | Click | Opens form |
| Save | Click with form filled | Reminder appears in list |
| Mark as complete | Click checkbox | Reminder marked done, moves to completed section |
| Edit | Click | Pre-fills form |
| Delete | Click → confirm | Removed from list |
| Filter: Active / Completed / All | Click each | List filters correctly |
| Sort by due date / priority | Click | List re-orders |

**PASS criteria:** Reminder saves with correct due date. Complete toggle works.

---

### Page 9 — Site Invoices (`/dashboard/sites/{SITE_ID}/invoices`)

```bash
curl -b /tmp/pk_cookies.txt -o /dev/null -s -w "%{http_code}" \
  http://187.124.26.127/dashboard/sites/{SITE_ID}/invoices
# Expect 200
```

**Create new invoice — fill every field:**

| Field | Test value |
|-------|-----------|
| Invoice number | `INV-QA-2026-001` |
| Client name | `QA Test Client GmbH` |
| Client email | `billing@qa-client.com` |
| Client address | `Bahnhofstrasse 1, 8001 Zürich, Switzerland` |
| Issue date | Today's date |
| Due date | 30 days from today |
| Line item 1 — Description | `Web development services` |
| Line item 1 — Quantity | `10` |
| Line item 1 — Unit price | `150.00` |
| Line item 1 — Tax | `7.7%` (Swiss VAT) |
| Add second line item | Click "Add item" |
| Line item 2 — Description | `Monthly hosting` |
| Line item 2 — Quantity | `1` |
| Line item 2 — Unit price | `49.00` |
| Notes / footer text | `Thank you for your business.` |
| Currency | `CHF` |

**Buttons:**

| Button | Action | Expected |
|--------|--------|----------|
| New invoice / Create | Click | Opens form |
| Add line item | Click | New row appears in line items table |
| Remove line item | Click × on a row | Row removed, totals recalculate |
| Save draft | Click | Invoice saved as draft |
| Mark as sent | Click | Status changes to Sent |
| Mark as paid | Click | Status changes to Paid |
| Preview / Print | Click | PDF preview or print dialog opens |
| Download PDF | Click | PDF file downloads |
| Edit invoice | Click | Pre-fills all fields |
| Duplicate invoice | Click (if present) | New draft created with same data |
| Delete invoice | Click → confirm | Invoice removed |
| Filter: Draft / Sent / Paid | Click each | List filters |
| Currency symbols in totals | Observe | No mojibake — `CHF`, `£`, `€` render correctly |

**Verify totals math:** With the test data above, totals should be:
- Line 1: 10 × 150.00 = 1,500.00
- Line 2: 1 × 49.00 = 49.00
- Subtotal: 1,549.00
- Tax (7.7%): 119.27
- Total: 1,668.27 CHF

If totals are wrong, report exact values shown.

**PASS criteria:** Invoice created, totals calculate correctly, currency symbols display without corruption, PDF generation works (if implemented).

---

### Page 10 — Site Settings (`/dashboard/sites/{SITE_ID}/settings`)

```bash
curl -b /tmp/pk_cookies.txt -o /dev/null -s -w "%{http_code}" \
  http://187.124.26.127/dashboard/sites/{SITE_ID}/settings
# Expect 200
```

**Form fields to edit:**

| Field | Test value |
|-------|-----------|
| Site name | `Test Site QA (Updated)` |
| Domain | `https://updated-testsite.com` |
| Timezone | Select a different timezone |
| Any notification toggles | Toggle each on and off |
| Any color/theme pickers | Change to a different color |
| Any text area fields | Fill with: `Updated during QA audit — April 2026` |

**Buttons:**

| Button | Action | Expected |
|--------|--------|----------|
| Save / Update settings | Click with changes | Success message, changes persist on page refresh |
| Cancel / Discard changes | Click (before saving) | Original values restored |
| Delete site button | Click | Confirm dialog appears — click CANCEL (do not delete the test site yet) |
| Any webhook / integration toggles | Toggle | State persists |

**PASS criteria:** Settings save and persist after hard refresh (`Ctrl+Shift+R`).

---

### Page 11 — Site Analytics (`/dashboard/sites/{SITE_ID}/analytics`)

```bash
curl -b /tmp/pk_cookies.txt -o /dev/null -s -w "%{http_code}" \
  http://187.124.26.127/dashboard/sites/{SITE_ID}/analytics
# Expect 200 (previously 500 — this confirms Fix 3 worked)
```

**Controls to test:**

| Control | Action | Expected |
|---------|--------|----------|
| Date range picker | Select "Last 7 days" | Chart updates (or shows empty state) |
| Date range picker | Select "Last 30 days" | Chart updates |
| Date range picker | Select custom range | Calendar opens, dates selectable |
| Chart type selector (if present) | Click each option | Chart re-renders |
| Disabled/stub controls | Observe | Greyed out with `opacity: 0.4`, not clickable |
| Export data (if enabled) | Click | CSV/JSON downloads |

**PASS criteria:** Page loads with HTTP 200. Empty state shown gracefully when no data. No 500 errors.

---

### Page 12 — Site Files (`/dashboard/sites/{SITE_ID}/files`)

```bash
curl -b /tmp/pk_cookies.txt -o /dev/null -s -w "%{http_code}" \
  http://187.124.26.127/dashboard/sites/{SITE_ID}/files
# Expect 200 (previously 500 — confirms Fix 2 worked)
```

**Buttons to test:**

| Button | Action | Expected |
|--------|--------|----------|
| Upload file | Click → select a test file (any image or txt file) | File uploads and appears in list |
| Create folder | Click → enter name `qa-test-folder` → confirm | Folder appears in directory listing |
| Navigate into folder | Click folder | Directory changes to show folder contents |
| Navigate back / breadcrumb | Click parent breadcrumb | Returns to parent directory |
| Rename file | Click rename → enter `renamed-test-file.txt` | File name updates |
| Delete file | Click delete → confirm | File removed from listing |
| Download file | Click download | File downloads |
| Sort by name / date / size | Click column headers | List re-orders |

**PASS criteria:** No 500. File list renders. Upload, create folder, rename, delete all work.

---

### Page 13 — Visual Editor (`/dashboard/sites/{SITE_ID}/editor`)

Navigate to the editor for an existing page (find a page from the site's page list).

```bash
curl -b /tmp/pk_cookies.txt -o /dev/null -s -w "%{http_code}" \
  "http://187.124.26.127/dashboard/sites/{SITE_ID}/editor?page=index"
# Expect 200
```

**What to verify:**

| Check | Expected |
|-------|---------|
| Code editor area | Shows actual code — NOT blank black screen |
| If no source file found | Shows error banner: "Source file could not be loaded: ..." — NOT blank |
| Undo button | Visually greyed out (`opacity: 0.4`), not clickable |
| Redo button | Visually greyed out, not clickable |
| Schedule button | Visually greyed out, not clickable |
| Save button | Clickable, saves and shows success toast |
| Mode toggle (code/visual) | Switches editor mode |
| Page selector dropdown | Opens list of site pages |
| Select a different page | Editor reloads with that page's content |

**Buttons:**

| Button | Action | Expected |
|--------|--------|----------|
| Save / Publish | Click after typing a small change | Success notification, change persists |
| Discard changes | Click | Reverts to last saved state |
| Full screen / expand | Click (if present) | Editor expands |
| Preview | Click (if present) | Preview panel or new tab opens |

**PASS criteria:** Editor shows code content (not blank). Stub controls visually disabled. Save works.

---

### Page 14 — Global Inbox (`/dashboard/inbox`)

```bash
curl -b /tmp/pk_cookies.txt -o /dev/null -s -w "%{http_code}" \
  http://187.124.26.127/dashboard/inbox
# Expect 200
```

**Verify:** Messages sent from site inboxes appear here. No mojibake in message previews.

---

### Page 15 — Global Settings / Profile (`/dashboard/settings`)

```bash
curl -b /tmp/pk_cookies.txt -o /dev/null -s -w "%{http_code}" \
  http://187.124.26.127/dashboard/settings
# Expect 200
```

**Form fields to fill:**

| Field | Test value |
|-------|-----------|
| Name | `Admin QA User` |
| Email | `admin@pixelkraft.com` (unchanged) |
| Current password | `admin123` |
| New password | `NewAdminPass2026!` |
| Confirm new password | `NewAdminPass2026!` |

**Buttons:**

| Button | Action | Expected |
|--------|--------|----------|
| Save profile | Click | Success message |
| Change password | Click with fields filled | Password updated |
| **Revert password** | Log out → log in with new password → change back to `admin123` | Confirm original password restored |
| Any 2FA / security toggles | Click | Modal or flow opens |
| Delete account | Click (if present) | Confirm dialog — click Cancel |

---

## Phase 4 — Cross-Cutting Checks

### Encoding audit — visual sweep

Navigate to each of these pages and look for any garbled characters (boxes, `â€™`, `Â£`, etc.):

- [ ] Inbox message preview
- [ ] Invoice line items and currency symbols
- [ ] Sidebar navigation labels
- [ ] Page titles in browser tab
- [ ] Any error messages shown in forms
- [ ] Email/notification templates (if accessible)

### HTTP status sweep — all known routes

```bash
ROUTES=(
  "/login"
  "/dashboard"
  "/dashboard/sites"
  "/dashboard/inbox"
  "/dashboard/reports"
  "/dashboard/expenses"
  "/dashboard/reminders"
  "/dashboard/invoices"
  "/dashboard/analytics"
  "/dashboard/settings"
  "/dashboard/sites/{SITE_ID}"
  "/dashboard/sites/{SITE_ID}/inbox"
  "/dashboard/sites/{SITE_ID}/reports"
  "/dashboard/sites/{SITE_ID}/expenses"
  "/dashboard/sites/{SITE_ID}/reminders"
  "/dashboard/sites/{SITE_ID}/invoices"
  "/dashboard/sites/{SITE_ID}/analytics"
  "/dashboard/sites/{SITE_ID}/files"
  "/dashboard/sites/{SITE_ID}/settings"
  "/dashboard/sites/{SITE_ID}/editor"
)

for path in "${ROUTES[@]}"; do
  REAL_PATH="${path//\{SITE_ID\}/1}"
  STATUS=$(curl -b /tmp/pk_cookies.txt -o /dev/null -s -w "%{http_code}" \
    "http://187.124.26.127$REAL_PATH")
  echo "$STATUS  $REAL_PATH"
done
```

**Expected:** All return `200`. Any `500` = unresolved bug. Any `302` to `/login` = session problem.

### Laravel error log — final check

```bash
tail -100 /var/www/pixelkraft/storage/logs/laravel.log | grep -E "ERROR|CRITICAL|Exception"
```

Any new errors introduced by the fixes should be resolved before closing this audit.

---

## Phase 5 — Cleanup

```bash
cd /var/www/pixelkraft

# Final cache clear
php artisan optimize:clear
php artisan view:clear

# Restart PHP-FPM to flush any in-memory state
systemctl restart php-fpm

# Confirm nginx is still running
systemctl status nginx --no-pager | head -5

# Remove the temp cookie file
rm -f /tmp/pk_cookies.txt
```

---

## Phase 6 — Audit Report

After completing all phases, produce a structured report in this format:

```
PIXELKRAFT PRODUCTION AUDIT REPORT
Date: [date]
Server: 187.124.26.127

GITHUB PULL
  Branch pulled: [branch name]
  Files changed: [N files]
  Migrations run: [list]

FIXES APPLIED
  Fix 1 — Livewire POST/auth:       [APPLIED / SKIPPED — reason]
  Fix 2 — FileManager import:       [APPLIED / SKIPPED]
  Fix 3 — Analytics schema guards:  [APPLIED / SKIPPED]
  Fix 4 — Editor empty buffer:      [APPLIED / SKIPPED]
  Fix 5 — Mojibake encoding:        [APPLIED — N files fixed]
  Fix 6 — Idempotent migration:     [APPLIED / SKIPPED]
  Fix 7 — Stub controls:            [APPLIED / SKIPPED]

HTTP STATUS SWEEP
  [list every route and its status code]

FORM & BUTTON QA RESULTS
  Login page:         [PASS / FAIL — details]
  Dashboard:          [PASS / FAIL]
  Create site:        [PASS / FAIL]
  Site inbox:         [PASS / FAIL — Livewire send working: Y/N]
  Site reports:       [PASS / FAIL]
  Site expenses:      [PASS / FAIL — currency symbols: Y/N]
  Site reminders:     [PASS / FAIL]
  Site invoices:      [PASS / FAIL — totals correct: Y/N, PDF: Y/N]
  Site settings:      [PASS / FAIL — persists on refresh: Y/N]
  Site analytics:     [PASS / FAIL — was 500, now: status]
  Site files:         [PASS / FAIL — was 500, now: status]
  Visual editor:      [PASS / FAIL — blank screen resolved: Y/N]
  Global settings:    [PASS / FAIL]

ENCODING SWEEP
  Mojibake found after fix: [Y — list locations / N — clean]

REMAINING ISSUES
  [List any failures that could not be resolved, with exact error messages]

NEW ISSUES INTRODUCED
  [List any regressions caused by the fixes]
```
