# Pixelkraft Deep Audit Report

Date: 2026-04-18
Workspace: `C:\Users\agonv\Documents\AG PK 2\pixelkraft-v2`
Scope: fresh code audit of the current repository state, plus a new verification pass on test/build/audit tooling.

This report supersedes the older workspace notes in `Audit.txt` and `Repo-App-Audit.txt`.

## Executive Summary

The repository is materially healthier than a broken project: tests pass, the frontend builds, and both dependency-audit commands are clean. The main risk is not "the app is collapsing"; it is that a small number of high-impact dashboard flows still trust dangerous operator input too much.

Two issues stand out above everything else:

1. Authenticated dashboard users can onboard a site from an arbitrary absolute host path.
2. That same onboarding model can combine with site deletion to recursively remove an arbitrary directory when the chosen slug matches the imported folder name.

There is also a second security theme: the active Inertia site-management routes bypass stricter validation rules that already exist elsewhere in the codebase, which re-opens repo URL and token-handling risk that the project had clearly already tried to solve.

## Verification Snapshot

Fresh checks run during this audit:

| Check | Result | Notes |
|---|---|---|
| `php artisan test` | Pass | `184 passed`, `1 skipped` |
| `npm.cmd run build` | Pass | Frontend build completed successfully |
| `composer audit` | Pass | No advisories reported |
| `npm audit --audit-level=high` | Pass | `0 vulnerabilities` |
| `composer phpstan` | Fail | 24 current errors |

Interpretation:

- Runtime regression risk is lower than it would be in a failing test/build state.
- Security exposure is driven more by application logic than by third-party package advisories.
- Static-analysis trust is currently degraded because `phpstan` is red even though the main test/build path is green.

## Findings

### 1. Critical: arbitrary host path onboarding is reachable from the authenticated dashboard flow

Evidence:

- The dashboard route group is protected by `auth` only, not an additional admin-only gate in this file: `routes/web.php:57-58`.
- Site creation accepts `source_type=server_path` in the active POST route: `routes/web.php:224-240`.
- The only server-path checks are "absolute path", "exists", and "readable": `routes/web.php:251-288`.
- The chosen host path is then persisted directly into `repo_path` and `deploy_path`, and also wrapped as a `file://` repo URL: `routes/web.php:303-311`.

Why this matters:

- Any authenticated dashboard user who can reach site creation can point Pixelkraft at an arbitrary readable directory on the host.
- There is no allowlist restricting imports to a safe base such as a managed repos root.
- That turns the site onboarding flow into a local filesystem attachment mechanism for arbitrary host paths.

Likely impact:

- Unauthorized reading/parsing of host content.
- Unintended attachment of operational directories to site lifecycle actions.
- Increased blast radius for later deploy, parse, and delete flows.

Recommended fix:

- Remove `server_path` from the user-facing creation route unless there is a tightly controlled admin-only need.
- If it must exist, require admin authorization and enforce that the resolved path lives under a configured allowlisted root.
- Store imported source paths separately from deploy paths instead of binding both to the same raw user input.

### 2. Critical: site deletion can recursively delete an arbitrary directory if the basename matches the chosen slug

Evidence:

- The create flow derives the slug from user-controlled site name: `routes/web.php:291-296`.
- For `server_path` sites, both `repo_path` and `deploy_path` are set to that raw imported directory: `routes/web.php:303-311`.
- When a site is cleaned up, `cleanupFilesystemArtifacts()` deletes `repo_path` and `deploy_path`: `app/Models/Site.php:568-576`.
- `deleteDirectory()` only refuses deletion when the basename does not match the expected slug: `app/Models/Site.php:613-636`.

Why this matters:

- A user can choose a site name that produces a slug equal to the basename of a real host directory.
- If that directory was imported through `server_path`, deleting the site can recurse into that host directory and remove it, subject only to process permissions.
- The basename check helps avoid obvious mistakes, but it is not a safe root-boundary check.

Likely impact:

- Destructive deletion of arbitrary host directories whose basename can be matched by a site slug.

Example abuse path:

1. Create a site named `foo`.
2. Import `/var/www/foo` through `server_path`.
3. Delete the site.
4. Cleanup logic treats `/var/www/foo` as an expected site-owned directory and removes it.

Recommended fix:

- Never delete directories that are not rooted under platform-owned storage paths resolved from config.
- Mark imported external directories as non-owned and exclude them from cleanup.
- Prefer a positive ownership model over basename heuristics.

### 3. High: active Inertia site-management routes bypass the stricter git-host validation already present in the codebase

Evidence:

- The live create route accepts `repo_url` as plain `nullable|string|max:500`: `routes/web.php:224-240`.
- The live settings update route does the same: `routes/web.php:639-670`.
- A dedicated `GitRemoteUrl` rule exists specifically to restrict repository hosts and reject `file://`, private hosts, and unknown external hosts: `app/Rules/GitRemoteUrl.php:8-27`, `app/Rules/GitRemoteUrl.php:41-91`.
- The older Livewire site manager still uses that stricter rule plus tighter branch/build validation: `app/Livewire/Sites/SiteManager.php:101-107`.
- The Livewire settings component also carries stricter validation for build output paths, deploy paths, branch syntax, and health-check URLs: `app/Livewire/Sites/SiteSettings.php:98-117`.

Why this matters:

- The project already contains safer validation logic, but the active dashboard flow no longer uses it.
- That creates security drift: the repository "knows" the safe rules, but the current production path is looser.

Likely impact:

- Unsafe repo URLs can enter the database through normal dashboard usage.
- Branch, path, and command fields are no longer normalized as strictly as the safer components intended.

Recommended fix:

- Extract the stricter rules into shared request objects or a single validation layer used by both create and settings flows.
- Do not keep a secure-but-unused validation path beside a weaker active one.

### 4. High: a malicious repo URL can still receive stored GitHub tokens

Evidence:

- The active routes allow arbitrary `repo_url` strings: `routes/web.php:224-240`, `routes/web.php:639-670`.
- `GitSyncService::buildAuthUrl()` inserts the stored token into whatever host is present in `repo_url`: `app/Services/GitSyncService.php:355-365`.
- `GitSyncService::writeCredentialFile()` also writes a credential helper entry for that parsed host: `app/Services/GitSyncService.php:401-415`.
- `GitRemoteUrl` was explicitly written to block unknown hosts that would otherwise receive the stored token: `app/Rules/GitRemoteUrl.php:19-26`.

Why this matters:

- If a user enters an attacker-controlled git host and also stores a GitHub token, Pixelkraft can hand that token to the attacker-controlled host during clone/pull/auth flows.
- This is not hypothetical; the code path is explicit.

Likely impact:

- Credential exfiltration of stored GitHub tokens.

Recommended fix:

- Apply `GitRemoteUrl` to every active repo URL entry point immediately.
- Reject non-allowlisted hosts before persistence.
- Consider host-binding credentials to an explicit provider field instead of deriving trust from a user-entered URL.

### 5. Medium-High: the media upload endpoint allows arbitrary file types onto the public disk

Evidence:

- Upload validation only checks `required|file|max:20480`: `routes/web.php:691-699`.
- Uploaded files are stored on the `public` disk and listed back through public URLs: `routes/web.php:679-689`.
- The frontend route currently does not even expose a manager UI; it just renders a placeholder: `resources/js/pages/sites/files.tsx:8-24`.

Why this matters:

- Authenticated users can upload HTML, SVG, or other active content under the app origin unless the web server separately blocks it.
- Same-origin hosted content can become an XSS, phishing, or unsafe file-hosting problem depending on headers and downstream usage.

Recommended fix:

- Enforce an allowlist by MIME type and extension.
- Serve user uploads from a separate host or non-executable bucket where possible.
- Add explicit content-disposition / content-type hardening for risky formats.

### 6. Medium: the queue architecture is documented as multi-queue, but actual jobs still dispatch to `default`

Evidence:

- Horizon is configured for queue separation across `default`, `git`, `parsing`, `deploy`, and `monitoring`: `config/horizon.php:212-250`.
- Diagnostics also expect those same queues to exist and be monitored: `app/Livewire/Settings/SystemDiagnostics.php:25-39`.
- Core jobs still bind themselves to `default`, including:
  - `app/Jobs/DeploySiteJob.php:22-27`
  - `app/Jobs/DeploySiteJob.php:35-40`
  - `app/Jobs/ParseSiteJob.php:24-28`
  - `app/Jobs/SyncFromWebhookJob.php:25-31`
  - `app/Jobs/SyncAnalyticsJob.php:21-25`

Why this matters:

- The application presents an isolated-queue architecture operationally, but critical workloads still compete in one lane.
- That weakens predictability under load and makes diagnostics less truthful than they appear.

Recommended fix:

- Move git, parse, deploy, and analytics jobs onto the queues the Horizon config already advertises.
- Align dashboard diagnostics with real dispatch behavior.

### 7. Medium: the safer platform deploy command exists, but the GitHub production workflow bypasses it

Evidence:

- `app:deploy` implements a safer sequence: maintenance mode, pause Horizon, migrate, clear caches, restart workers, bring the app back up: `app/Console/Commands/AppDeploy.php:8-31`, `app/Console/Commands/AppDeploy.php:40-58`.
- The production GitHub workflow instead does direct SSH deployment with `git reset --hard`, `git clean -fd`, package install/build steps, then `optimize:clear` and `horizon:terminate`: `.github/workflows/deploy-production.yml:97-127`.

Why this matters:

- There are now two deployment stories for the platform itself.
- The safer one is documented in code, but the automated production path is using the rougher one.
- This increases operational drift and makes deploy behavior harder to reason about.

Recommended fix:

- Make the GitHub deploy workflow call `php artisan app:deploy` after syncing code, or fold the workflow into the same deployment sequence.

### 8. Medium: several user-visible sections are still placeholders even though navigation and docs present a broader finished product

Evidence:

- The sidebar exposes routes for Templates, Subscribers, Inbox, Analytics, Media, and more: `resources/js/layouts/AppLayout.tsx:34-53`.
- Several of those pages are still placeholder UIs:
  - `resources/js/pages/sites/files.tsx:17-20`
  - `resources/js/pages/sites/templates.tsx:17-20`
  - `resources/js/pages/analytics/index.tsx:12-16`
  - `resources/js/pages/email/campaigns.tsx:12-16`
  - `resources/js/pages/email/inbox.tsx:12-16`
  - `resources/js/pages/email/subscribers.tsx:12-16`
- The README markets these areas as established platform capabilities, including "Newsletter subscriber management & campaign editor" and a broad automation/ops story: `README.md:60-100`.

Why this matters:

- This is less a security bug than a trust and product-readiness issue.
- The dashboard advertises workflows that are not actually delivered end-to-end yet.

Recommended fix:

- Hide unfinished routes from primary navigation or label them clearly as beta/coming soon.
- Tighten README claims so the repository description matches current UX reality.

### 9. Medium: campaign content/API capabilities are richer than the current runtime and UI usage suggests

Evidence:

- The public campaigns endpoint claims it is consumed by JavaScript running on managed sites: `app/Http/Controllers/Api/ActiveCampaignsController.php:10-19`.
- It returns fields such as `trigger_delay_ms`, `target_pages`, `dismissal_rules`, and `locale`: `app/Http/Controllers/Api/ActiveCampaignsController.php:29-70`.
- The tracking script currently only handles analytics collection and basic event capture; it does not fetch or render campaigns/announcements: `app/Services/TrackingScriptService.php:19-73`.
- The campaign model supports richer state, including `target_pages`, `audience_conditions`, `dismissal_rules`, and `trigger_delay_ms`: `app/Models/Campaign.php:12-29`, `app/Models/Campaign.php:38-70`.
- The current site campaign UI exposes only a much smaller subset of that model: `resources/js/pages/sites/campaigns.tsx:25-40`, `resources/js/pages/sites/campaigns.tsx:79-110`.

Why this matters:

- The backend/API model is ahead of the delivered runtime integration and ahead of the operator UI.
- This is a classic partial-feature smell: implemented primitives without the final consumer.

Recommended fix:

- Either wire a real managed-site runtime consumer for the campaigns API, or narrow the model/API surface until the runtime exists.

### 10. Medium: static analysis is currently noisy enough to reduce confidence in CI as a regression detector

Evidence:

- `composer phpstan` currently fails with 24 errors.
- The baseline still contains many relation/property suppressions across models and Livewire components, showing the type layer is out of sync with the current codebase: `phpstan-baseline.neon:1-220`.

Why this matters:

- The project has green tests and a green build, but the static-analysis lane is not trustworthy.
- That makes it easier for real type/regression issues to hide among stale relation and property noise.

Recommended fix:

- Reduce the baseline instead of normalizing around it.
- Start with the highest-signal areas: `Site` relations, dashboard Livewire components, and the diagnostics namespace issues already surfaced by the current run.

## Positive Findings

Important things that are working well in the current state:

- Core verification gates are green except for static analysis.
- Dependency advisories are clean in both Composer and npm.
- There is clear evidence of defensive intent in the codebase:
  - dedicated git-host validation rule
  - webhook signature verification
  - secret scrubbing in git-related notifications
  - safer deploy command for the platform
  - multiple path-traversal and URL-safety protections elsewhere in the codebase
- The repository is not suffering from broad build instability right now; the biggest issues are concentrated and fixable.

## Priority Order For Remediation

If the team wants the shortest path to materially lowering risk, the order should be:

1. Lock down or remove `server_path` onboarding and block external-directory cleanup.
2. Reconnect active create/settings routes to the stricter shared validation rules, especially `GitRemoteUrl`.
3. Restrict public uploads by MIME/extension and consider isolating their serving domain.
4. Align actual job dispatch queues with Horizon/diagnostics expectations.
5. Unify production deployment on the safer `app:deploy` flow.
6. Either finish, hide, or clearly mark the placeholder dashboard sections.
7. Pay down the current PHPStan error set so CI regains signal.

## Bottom Line

Pixelkraft is not in a "rewrite it" state. The codebase has a working spine and a decent safety culture in parts of the implementation.

The immediate concern is that the currently active dashboard site-management flow is more permissive than the safer validation and ownership boundaries already present elsewhere in the repository. Fixing that drift would remove the most serious risk much faster than any broad refactor.
