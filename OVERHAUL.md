
```
CONTEXT
-------
Pixelkraft is a Laravel + React self-hosted site operations platform.
URL: https://dashboard.pixelkraft.pro/
Admin credentials: admin@pixelkraft.com / admin123

The current UI is a custom-built design. Your job is to migrate the entire frontend to 
shadcn/ui (Radix primitives + Tailwind CSS) without removing or breaking any existing 
functionality, and implement the new features listed below.

Stack: Laravel backend, Inertia.js/React frontend, Tailwind CSS.
Target: shadcn/ui component library, Lucide icons, Inter font.

---

PHASE 1 — SHADCN/UI MIGRATION (All Pages)
------------------------------------------
Audit every page and component. Replace all custom-built UI with shadcn/ui equivalents.
Do not build custom components when a shadcn primitive exists.

Mapping:
- All form inputs       → shadcn Input, Select, Checkbox, Switch, Textarea, Label
- All modals/dialogs    → shadcn Dialog / Sheet
- All dropdowns         → shadcn DropdownMenu
- All tables            → shadcn DataTable (TanStack Table)
- All buttons           → shadcn Button (correct variant per context: default, outline, 
                          ghost, destructive)
- Toasts/alerts         → shadcn Sonner
- Cards/panels          → shadcn Card
- Sidebar navigation    → shadcn Sidebar (collapsible, icon + label)
- Sub-page tabs         → shadcn Tabs
- Status indicators     → shadcn Badge
- Progress bars         → shadcn Progress
- Tooltips              → shadcn Tooltip on every icon-only button (mandatory)
- Async loading states  → shadcn Skeleton

Pages to migrate (confirmed from live app):
  Global: Dashboard, All Sites list
  Per-site: Inbox, Reports, Campaigns, Expenses, Invoices, Reminders, Analytics, 
            Maintenance, Media, File Manager, Deploy Controls, SEO Editor
  Settings: Profile, Change Password, 2FA, Discord, API Tokens, System Diagnostics
  Visual Editor: Layers panel, Properties panel, toolbar

Typography: Inter. Colors: CSS variables (--background, --foreground, --primary, 
--muted, --destructive) so dark/light mode toggle works (toggle already exists in nav).

Fully responsive. Sidebar collapses to a Sheet on mobile. Tables scroll horizontally 
at narrow widths.

---

PHASE 2 — NEW FEATURE IMPLEMENTATIONS
---------------------------------------

### 2A. Two-Factor Authentication (2FA)
The Settings page already has an "Enable 2FA" button — it is not yet functional.
Implement full TOTP-based 2FA using Laravel Fortify's built-in two-factor system.

- Settings → "Enable 2FA" opens a Dialog:
    Step 1: Show QR code (pragmarx/google2fa) + manual entry key.
    Step 2: Confirm with a 6-digit OTP before activating.
    Step 3: Show 8 single-use recovery codes in a copy-to-clipboard block. 
            User must acknowledge before closing.
- After enabling: button changes to "Disable 2FA" (requires password re-confirmation).
- On login: if 2FA is active, redirect to /two-factor-challenge — a minimal page 
  with shadcn InputOTP (6 digits) + a "Use recovery code" link below.
- Recovery code input: plain text field, single-use, consumed on success.

### 2B. Site Source: Server Path + Direct Upload
The current "Add New Site" form (4 steps) supports GitHub only (step 3: Repository & 
deployment). Extend step 3 with a source type selector:

Source type segmented control at the top of step 3 (3 options):

  A — GitHub (existing, unchanged):
      Fields: GitHub repository URL, Branch, Build command (optional).

  B — Server Path:
      Field: "Absolute path on this server" (e.g. /var/www/mysite).
      On next: validate path exists + is readable via GET /api/v1/validate-path?path=...
      No build pipeline triggered. Nginx config points to that path directly.

  C — Direct Upload:
      Drag-and-drop zone (react-dropzone). Accepts .zip only.
      On upload: backend extracts to /storage/sites/{site_slug}/, detects package.json,
      runs build if found, serves result.
      Show extraction + build progress via polling /api/v1/sites/{id}/import-status.

### 2C. CMS Auto-Run Dev Server
In Site Settings, add a new tab: "Dev Server".

- Toggle: "Auto-run dev server on import" — only shown for framework sites 
  (Next.js, Vite, Nuxt, SvelteKit, Astro).
- If enabled: after clone/upload, backend spawns `npm run dev` (or pnpm/yarn/bun, 
  auto-detected) and tracks the process in a SiteProcess model (PID, status, port).
- Dev server status badge shown in the site card and site detail header:
  Running (green) / Stopped (zinc) / Errored (red).
- Controls on the Dev Server tab: Start, Stop, Restart buttons.
- Live log viewer on that tab: terminal-style, dark background, monospace font, 
  streaming via SSE or 2s polling of /api/v1/sites/{id}/dev-log.

### 2D. "Add New Site" — Blocking Import Progress Modal
When the user submits the completed Add New Site form, open a full-screen non-dismissible 
Dialog immediately. No close button. ESC key disabled. Background locked.

Show a vertical step tracker with these steps in order:
  1. Validating credentials
  2. Cloning repository / Extracting upload / Mounting path
  3. Detecting package manager
  4. Installing dependencies
  5. Running build
  6. Generating Nginx config
  7. Analysing SEO metadata
  8. Saving to database

Each step:
  - Active: spinner icon + step name in foreground color.
  - Complete: green check icon.
  - Failed: red X icon + expandable error message (shadcn Collapsible) 
    + "Retry from this step" button.

On full success: auto-close after 2 seconds, redirect to new site's dashboard.
On unrecoverable failure: show a "Cancel and delete this site" link at the bottom.

### 2E. Integrations Page
Add a new section in Settings: "Integrations". Display as a grid of cards 
(shadcn Card), each with: logo, name, description, status badge, Connect/Disconnect 
button.

The Add New Site form already has DNS provider (Cloudflare) and SSL provider 
(Let's Encrypt) selectors. These should pull their credentials from the global 
Integrations settings, not ask per-site.

Integrations to build:

1. Cloudflare
   Fields: API Token, Zone ID (global default, overridable per site in Site Settings).
   After connect: show zone name + plan tier from Cloudflare API in the card.
   Behaviour: auto-purge Cloudflare cache on every successful deploy.

2. Let's Encrypt
   Fields: Registration email.
   Per-site toggle in Site Settings → Domain: "Provision SSL via Let's Encrypt".
   On enable: queue a certbot --nginx job, stream status to a toast.
   Show certificate expiry date in the site detail header next to the SSL badge 
   (currently shows "Pending — Not provisioned").

3. Hosting / SSH
   Fields: Host, Port, Username, Private Key (encrypted textarea).
   Purpose: remote deploys on external servers (not just the local machine).
   "Test Connection" button: SSH ping → returns latency or error inline.

4. Media Storage
   Provider selector: Local Disk / S3-compatible / Cloudflare R2 / FTP.
   Fields change per provider (endpoint, bucket, key, secret, region, etc.).
   Once connected: visual editor image uploads route to this storage instead of 
   local disk.

All credentials stored encrypted. Every integration has a "Test connection" step 
before saving credentials.

### 2F. Expanded Edit / Delete Controls
These pages exist but are missing edit/delete capability. Add it everywhere:

Expenses (currently: add-only form + table with no actions):
  - Inline row edit: click row → expand edit form in-place.
  - Delete per row with confirmation dialog (destructive button).
  - Checkbox column for bulk delete.
  - "Export CSV" button above the table.

Reports (currently: save form + empty history):
  - Each history row: Edit (open form pre-filled), Duplicate, Archive (soft delete), 
    Delete (hard, with confirmation).
  - Archived reports accessible via a toggle ("Show archived").

Pages (Site → Pages table, currently has only "SEO" and "Edit" actions):
  - Add "Remove page" action per row.
  - Confirmation dialog copy: 
    "This will permanently remove this page from your site and delete the source file. 
     Type the page slug to confirm."
  - type-to-confirm input using shadcn Input, submit button disabled until slug matches.

Invoices (currently: create + preview):
  - Per-invoice actions: Mark as paid, Duplicate, Download PDF, Delete.
  - "Mark as paid" changes badge from Outstanding → Paid and records payment date.

Campaigns:
  - Per-campaign: Edit, Duplicate, Delete, Enable/Disable toggle.

Reminders:
  - Per-reminder: Edit inline, Delete with confirmation, Mark complete (strike-through).

Site list (All Sites):
  - Checkbox column for bulk actions: Bulk deploy, Bulk delete, Bulk toggle monitoring.

---

PHASE 3 — VISUAL EDITOR OVERHAUL
----------------------------------
The visual editor has three zones that need redesigning. Do not remove any existing 
functionality (Layers panel, Pages, Media tabs, CODE/VISUAL element types, 
locked/managed states, Properties, SEO, History, Code tabs on the right).

Current state (confirmed from live app):
  - Top bar: Desktop/Tablet/Mobile toggles | Code toggle | Schedule | Save draft | 
    Preview | Publish — disorganised, no tooltips, no undo/redo.
  - Left sidebar: Layers tab shows a flat-ish list of body > elements with CODE/VISUAL 
    labels and locked/managed badges. Pages and Media tabs also exist.
  - Right panel: Properties / SEO / History / Code tabs — functional but dense.
  - Editable elements in the iframe: no visible borders shown.

### Top Toolbar (redesign, keep all actions)
Reorganise into one compact bar with three clusters:

LEFT:
  ← back arrow (confirm if unsaved) | Site name / Page name breadcrumb 
  (click page name → dropdown to switch page)

CENTER:
  [Edit | Preview | Code] — shadcn ToggleGroup, single selection
  [Desktop | Tablet | Mobile] — icon buttons with Tooltip labels

RIGHT:
  Undo (Cmd+Z) | Redo (Cmd+Shift+Z) — ghost icon buttons with Tooltip showing shortcut
  Schedule button (ghost, clock icon)
  Save Draft (outline button)
  Publish (default/primary button)
  Avatar → DropdownMenu (Account, Exit editor)

Every button in the toolbar must have a Tooltip. No unlabelled controls.

### Left Sidebar — Layers Panel (redesign, keep CODE/VISUAL/locked/managed logic)
Replace the current flat list with a proper tree view:

- Panel header: "Layers" label + search input (filters tree by label).
- Tree: indented by nesting level (16px per level), chevron to expand/collapse parents.
- Each row: element type icon + inferred label + CODE or VISUAL badge + locked/managed 
  indicator (lock icon, muted color when locked).
- Hover a row → dashed highlight on corresponding element in iframe.
- Click a row → selects element, highlights in iframe, populates right panel.
- Right-click row → ContextMenu: Select, Duplicate, Delete, Move Up, Move Down, 
  Lock/Unlock.
- Locked elements: row is muted + italic, no right-click destructive actions.
- "Pages" and "Media" tabs remain as separate tabs next to "Layers" in the panel header.

"Add Element" button pinned to bottom of layers panel:
  Opens a small popover palette: Text, Image, Button, Divider, Container, Custom HTML.

### Right Panel — Properties Panel (reorganise, keep all existing tabs)
Keep the tab bar: Properties | SEO | History | Code.

Under "Properties" tab, reorganise into shadcn Accordion sections (collapsible):
  1. Content     — text editor, href (for links), src + alt (for images).
  2. Layout      — display, flex/grid controls, padding, margin (visual box model).
  3. Typography  — font, size, weight, line-height, letter-spacing, color.
  4. Background  — solid color picker, image upload, gradient.
  5. Border & Shadow — per-side border (width/style/color/radius), box-shadow.
  6. Attributes  — raw key/value pairs (aria-*, data-*, id, class).

All property inputs update the element live in the iframe on change.
Commit to source only on Save Draft / Publish.

### Editable Element Borders in the Iframe
When the editor parses editable elements, render a selection overlay — not a CSS 
border (which shifts layout) — using an absolutely positioned overlay div or outline:

Rules (all borders: 1px solid, no glow, no shadow, no thick outlines):
  Hovered, not selected  → 1px solid #6366f1 (indigo) at 50% opacity
  Selected               → 1px solid #6366f1 (indigo) at 100% opacity
  Text elements          → 1px solid #22c55e (green)
  Image elements         → 1px solid #f59e0b (amber)
  Interactive (button, a, input) → 1px solid #ef4444 (red)
  Container / layout (div, section, article) → 1px solid #8b5cf6 (violet)

Selected element also shows:
  - Corner + edge resize handles (small 4px filled squares, same color as border).
  - A floating pill label above the top-left corner: element tag name 
    (e.g. "section", "img", "a") in a pill with matching border color as background.

Locked elements: border is dashed, color = zinc-400, no resize handles, 
no selection on click (show a tooltip: "This element is locked").

---

IMPLEMENTATION ORDER
---------------------
Execute in this order. Do not start a phase before the previous one is complete and 
tested:

  1. shadcn/ui setup: install library, configure CSS variables, set up theming.
  2. Phase 1: migrate all existing pages to shadcn/ui components.
  3. Phase 2A: 2FA (self-contained, Settings page already has the button stub).
  4. Phase 2D: Blocking import modal (high impact, needed before 2B/2C).
  5. Phase 2B + 2C: Additional site source types + dev server tab.
  6. Phase 2E: Integrations page.
  7. Phase 2F: CRUD expansions across all pages.
  8. Phase 3: Visual editor overhaul (most complex, last).

---

QUALITY REQUIREMENTS (non-negotiable)
--------------------------------------
- Every async action: loading state (Skeleton or spinner).
- Every destructive action: shadcn Dialog with Button variant="destructive".
- Every form: inline validation via shadcn FormMessage.
- All credential/password inputs: show/hide toggle.
- No React warnings. No TypeScript errors. No console errors.
- Mobile tested at 375px minimum width.
- The live app URL throughout all backend references must be:
  https://dashboard.pixelkraft.pro/
  (Do not use the old IP 187.124.26.127 anywhere in new code.)
```