# Operations runbook

Skeleton created at M0. Each section names the issue that completes it; "TODO" marks only what a later issue fills. No secret values belong here. Context: [Deployment](/build/deployment), [Security](/build/security), [Launch checklist](/build/launch-checklist).

## Local environment

- WordPress Studio is the main path: `powershell -File deploy/local/setup.ps1` in `site/`. Optional: `npx wp-env start` with Docker. See `site/README.md`.
- Studio uses SQLite; PHPUnit integration tests needing MariaDB wait on the test-environment issue (#366). Mail catcher: #367.
- Owner issue: M0-INF-02 (done).

## Code quality settings

| Tool | Setting | Owner issue |
|---|---|---|
| PHPCS | WordPress-Extra, WordPress-Docs, PHPCompatibilityWP 8.3+, prefixes `wiw`, `WIW`, `WhoIsWho` | M0-INF-03 |
| PHPStan | Level 6 with the WordPress extension, no baseline; raise per module | M0-INF-04 |
| Assets | esbuild, content-hashed output in `dist/` | M0-INF-06 |

## Deploy

A release is a built artifact, never a checkout. Steps, in order (the owner approves the first go-live; later deploys follow the same steps):

1. On the build machine, from `site/`: `deploy/build-release --version <label>`. It builds pinned WordPress core, the plugins from the lockfile, the first-party plugin, the guard mu-plugin, both themes (parent `whoiswho` and child `whoiswho-child`), the production autoloader and built assets, and refuses to finish if a theme is missing or a `.env`, test or secret file is in the artifact. Output: `build/release/` and a `.tar.gz`.
2. Print the go-live checklist for the day: `deploy/migrate-live --print-checklist`.
3. Rehearse: `deploy/migrate-live --release <unpacked artifact> --target <web root> --secrets <secrets file> --dry-run`. It prints every step and changes nothing.
4. Run it for real on the live host: `deploy/migrate-live --release <artifact> --target <web root> --secrets <secrets file> --url https://<site> --production` (add `--db-import <dump.sql> --uploads <dir>` for the first go-live, and `--releases-layout` to keep each release in `releases/<timestamp>` with a `current` symlink). It copies the code, checks the secrets file is outside the web root, takes an encrypted pre-migration database dump, runs the forward-only migrations (the only way migrations run in production) and runs the smoke checks. It never prints a secret.
5. Confirm the smoke check (below) is all PASS and paste its output into the release notes.
6. Confirm the child theme is the active theme and the parent is present (the script checks this; REQ-273).

Local rehearsals use `php bin/wiw.php migrate` instead of the script. Never edit files on the host by hand.

### Smoke check after every deploy (M5-LCH-03)

From the site folder on the host: `php deploy/smoke-check.php --url=https://<site> --gone-path=/profile/<an-unpublished-slug> [--healthz-token=<token>]`. It prints one PASS, FAIL or SKIP line per check and exits non-zero if any check fails (a run with no passes also fails). Staging and live use the same command. Paste the output into the release notes. Add `--no-wp` to run only the HTTP checks from another machine.

## Updates and plugin policy (M5-OPS-08, launch checklist items 8 and 9; REQ-046, REQ-065)

**Rule.** Nothing updates itself on the live site and nothing updates from wp-admin. `AUTOMATIC_UPDATER_DISABLED` and `WP_AUTO_UPDATE_CORE` are off in `wp-config.php`, the guard mu-plugin turns off core, plugin, theme and translation auto-updates, and file editing is disabled. Every update, core included, is tried locally, ships as a release through the pipeline (`deploy/build-release`, then `deploy/migrate-live`) and is applied by the Administrator.

> Reading of the brief: "core minor and security updates are automatic" is met by applying them promptly through the pipeline, not by switching WordPress auto-update on. This keeps one deploy path and means a release is always the thing that was tested. If the owner prefers WordPress's own minor auto-update, change `WP_AUTO_UPDATE_CORE` to `'minor'` in the wp-config template and remove the `auto_update_core` filter in the guard; nothing else depends on it.

| Kind of update | Where tried first | When applied | By |
|---|---|---|---|
| Security fix (core, plugin, theme, dependency) | Locally, with `composer test` and the visibility matrix | Within 48 hours of the advisory | Administrator |
| Core minor release | Locally | Weekly batch, or with the next security fix | Administrator |
| Core major release | A local copy of the live data; full test suite, e2e and accessibility runs | After the local test passes and the owner approves; never in the weekly batch by default | Administrator, owner approves |
| Plugin and theme updates, PHP and JavaScript dependencies | Locally | Weekly batch (Monday, after the Dependabot pull requests arrive) | Administrator |

**How an update ships.**

1. Dependabot opens weekly pull requests (Mondays: Composer and npm in `site/`, GitHub Actions at the root). Auto-merge is not enabled; an Administrator reads each one.
2. Check the changelog for breaking or data-handling changes, run `composer test`, `composer test -- --testsuite Visibility`, `composer analyse`, `composer audit` and `npm audit --audit-level=high`, and let CI run the same gates.
3. Merge, build the release, apply with the go-live script, run the smoke check, record the result in the release notes.
4. A security advisory that arrives outside the weekly batch starts the 48-hour clock when it is published; do not wait for Monday.

**Adding or changing a plugin.** Every plugin is pinned (`composer.lock`) and listed with its reason in `site/config/plugin-allowlist.json`; `node scripts/check-plugins.js` fails the build if the two disagree or a forbidden kind is added (SEO, public login or membership, payment, AI drafting, page builder). The change needs a pull request citing [Third-party plugins](/plugins/third-party), a privacy check, an entry in the processor register when it touches personal data, and a passing visibility matrix: the `visibility` CI job runs the whole matrix on every pull request, so a plugin cannot merge without it. Rerun it locally with `composer test -- --testsuite Visibility` before opening the pull request.

**Weekly custom-plugin review** (scheduled in the Administrator's calendar, launch checklist item 8; D-060). Covers `whoiswho-core`, `whoiswho-guard` and the two themes:

- [ ] List the commits merged since last week (`git log --since="1 week ago" -- site/wp-content`); read the diff of each, not just the titles.
- [ ] Look for the risk patterns: unescaped output, a missing nonce or capability check, raw `$_GET` or `$_POST`, unprepared SQL, `eval`, a new outbound request, a new place that reads profiles outside the `PublicProfile` and `PublicStory` read models, anything that writes `pub_state`, Verified or Featured outside its one owner.
- [ ] Confirm the test and architecture suites are green on the default branch and the visibility matrix covers any new public surface.
- [ ] Check new personal-data fields are in `config/pii.php` and new processors are on the register.
- [ ] Update the dependency register: each package, version, licence, last review date; note anything abandoned or with an open advisory.
- [ ] Look at the failed-job alerts, the audit log verify result and the backup check.
- [ ] Record the date, reviewer and findings (issues opened for anything found) in the release notes.

## Pre-launch audit (M5-SEC-05; REQ-046, REQ-065)

Run once before go-live and paste the output into the release notes: `node scripts/prelaunch-audit.js` (from the repository root; add `--skip-network` offline). It prints PASS, FAIL or SKIP per check and exits non-zero on any FAIL.

| Check | What it does | Also enforced in CI |
|---|---|---|
| composer audit and npm audit | `composer audit --locked`, `npm audit --audit-level=high` | `audit` job on every pull request and weekly (`security-weekly.yml`); a finding fails the run and the test `test_REQ_046_ci_fails_on_high_severity_finding` guards that wiring |
| Secrets in history | Runs `gitleaks` when installed; otherwise scans every added line of every branch for private keys, tokens, real salts and pasted DB passwords | `secrets` job, full history (`fetch-depth: 0`) |
| Plugin allowlist | `scripts/check-plugins.js`: pinned, allowlisted, no forbidden kind | `plugins` job |
| Plugin register | Every allowlisted plugin is named in `docs/plugins/third-party.md` | not in CI (the register is a docs page) |

Two steps are by hand, because no vulnerability database is wired in and the Administrator's approval is a person's act:

1. **Plugin vulnerabilities.** For each plugin on the allowlist, look up its exact version in a WordPress vulnerability database (Patchstack or WPScan, both need an account; the choice is open) and in the plugin's own changelog. `composer audit` already covers any package that has a Packagist advisory, so a clean result is necessary but not enough for WordPress.org plugins. Record the database, date and result per plugin.
2. **Approval.** The Administrator confirms each installed plugin was approved and is in the register with its reason. The script's last line stays SKIP until then.

## Rollback

Rule: migrations are forward-only and additive (expand, then contract), so the previous release runs against the new schema. Roll back code, not the database.

- **Bad code release.** With `--releases-layout`, point `current` back at the previous `releases/<timestamp>` folder (instant), then run the smoke check. Without it, redeploy the previous artifact with `deploy/migrate-live --release <previous artifact> --target <web root> --secrets <secrets file>`.
- **Bad migration (a schema step that must be undone).** Do not edit tables by hand. Redeploy the previous artifact (the previous code ignores added columns and tables). If data was damaged, `deploy/migrate-live --rollback --release <previous artifact> --target <web root> --secrets <secrets file> --restore-dump <the pre-migration dump from that deploy>` redeploys it and restores that encrypted dump. This loses everything written since the deploy, so use it only in the first minutes and tell the owner first.
- **Bad import.** Imported profiles carry their import batch. In the admin open Directory, then Profiles, filter by Batch, check the count and the first names shown, give a reason and choose Unpublish these profiles (see Bulk unpublish). Every profile in the batch answers 410 and leaves search and the sitemap; Restore this batch reverses it. Fix the source file and import again.
- **Bad publication (a profile or story that should not be public).** Unpublish it from Directory, then Profiles (row action, reason required) or from its review screen; for a story set it to draft. A profile that must never return is a removal request: use Compliance, then Requests and reports, Valid: hide and suppress, which also keeps the name off future imports. Then check the page answers 410 (profile) or 404 (story) and look at the Purge log screen to confirm the public cache was purged.

## Backup restore

Owner decision: backups, restore tests and cPanel backups are outside the scope of this build, so this runbook does not promise them and no restore test is recorded. The only database copy the project takes is the encrypted pre-migration dump that `deploy/migrate-live` makes at every deploy (kept beside the secrets file in `wiw-dumps/`, outside the web root; the passphrase is `BACKUP_PASSPHRASE` in the secrets file). It is used only by `deploy/migrate-live --rollback` (see Rollback). If the owner later adds scheduled backups, add the schedule, the off-server copy and a dated restore test here.

## Incidents

1. **Who is told.** The alert contact named under Who receives alerts. Staff who notice a problem tell that person first.
2. **First checks (five minutes).** Open the public home page, a profile, Stories and the search page; run the smoke check (`php deploy/smoke-check.php --url=https://<site> ...`); look at Tools, Site Health and the PHP error log in cPanel; check the Email log and Purge log screens for failed jobs and run `php bin/wiw.php jobs:run` to see whether queued jobs complete.
3. **Contain.** If wrong information is public, unpublish it first (see Rollback, bad publication) and ask questions after. If the whole site must go down, put WordPress in maintenance (create `.maintenance` in the site root, or use cPanel to point the domain at a static page) and tell the alert contact.
4. **Suspected personal-data breach.** Follow the Personal-data breach procedure below; do not wait to finish diagnosing.
5. **Write the note.** Copy the Incident template at the end of this section: what happened, when it started and ended, who was affected, what was done, what changes. No personal data in the note (use ids and counts).

### Personal-data breach procedure (M3-PRV-18, REQ-066)

This is an offline procedure. There is no breach-log screen and no automatic regulator notification (D-067). A breach is any loss, theft, unauthorised access, disclosure or alteration of personal data held by the site: staff or enquirer details, claim and correction records, newsletter subscribers, the audit log, backups, or a leaked secret that gives access to them. Treat a suspected breach as a breach until it is ruled out.

**Clock.** Write down the time you became aware (UTC). The regulator and affected-people notices are due within the legal deadline for the data subjects' country; the usual outer limit is 72 hours from awareness (Nigeria Data Protection Act, GDPR). The Administrator confirms the exact rule with Legal at the first step and records it in the incident note. Do not wait for certainty before starting the clock.

**1. Detect and report.** Anyone who sees a sign (unexpected admin login, audit-log gap, alert from a failed job, a host or plugin security notice, a report from a user, a lost laptop or backup) tells the Administrator immediately. Sources to check: the Audit log screen and its verify action (`php bin/wiw.php audit:verify` once M0-PLG-07 is merged), the host's access logs, failed-login alerts.

**2. Contain** (first hour; the person on call does these without waiting for approval):

- [ ] Take the site to maintenance or switch off the affected form or screen (the Nominations flag, a plugin) if data is still leaking.
- [ ] Revoke the access that was used: disable the staff account, rotate the secret involved (see Key rotation), rotate the WordPress salts to end all sessions, rotate the database password and SMTP credentials if the host is suspect.
- [ ] Preserve evidence before changing anything: copy the host logs and a database dump (encrypted with `BACKUP_PASSPHRASE`) to the restricted incident folder.
- [ ] Do not delete data and do not restore a backup over the evidence until it is copied.

**3. Assess** (within 24 hours):

- [ ] What kinds of data (categories only, for example "email addresses of enquirers"), how many people, which countries.
- [ ] How it happened, when it started, whether it has stopped.
- [ ] Likely harm to the people: low, or likely to cause risk to their rights; high if contact details with context, or anything that could expose someone to harm.
- [ ] Whether a processor (host, email provider) is involved; ask them for their own report.
- [ ] Decision: notify the regulator? notify the people? Record who decided and why. A breach unlikely to risk people's rights may need only an internal record; when in doubt, notify.

**4. Notify** (inside the legal deadline):

- [ ] Regulator: the Administrator sends the notice (what happened, categories and approximate numbers, likely consequences, measures taken, contact point). A first notice may be incomplete; send it on time and follow up.
- [ ] Affected people, when the risk to them is high: plain language, what happened, what it means for them, what we have done, what they should do, who to contact. Send from the support address, not through bulk newsletter tooling.
- [ ] Processors and partners who need to act.

**5. Recover and learn:**

- [ ] Fix the cause through the normal pipeline (issue, tests, pull request); a discovered weakness becomes an issue labelled `type:discovered`.
- [ ] Restore service; run the smoke check (`php deploy/smoke-check.php`) and the visibility matrix.
- [ ] Hold a short review within two weeks; update this procedure if it failed.

**Incident log.** Each incident is one dated entry kept in this section (or in the restricted incident folder when it must name data locations). Entries never contain personal data: no names, emails, profile text from a request, or request bodies; refer to people by count and category. Template:

```
### Incident YYYY-MM-DD-NN
- Aware at (UTC):            Reported by (role):
- Contained at (UTC):        Deadline for notices (UTC):
- What happened (no personal data):
- Data categories and approximate number of people:
- Countries of the people:   Processor involved:
- Likely harm:               Decision on regulator / people, by whom, why:
- Regulator notified (date, reference):   People notified (date, how):
- Cause and fix (issue and PR numbers):
- Review held (date) and changes made:
```

No incident has been recorded yet.

## Bulk unpublish

Who: holders of `wiw_bulk_unpublish` (Reviewer and Administrator by default). Where: Directory, then Profiles; set the filters (name, state, type, origin, source, batch) and the panel shows how many published profiles match and the first five names. Give a reason (no personal data) and choose Unpublish these profiles; the count shown must still match when you submit or it is refused. The work runs as a background job through the state machine, so each profile is audited with the batch reference and the reason, then answers 410 and leaves search and the sitemap. To reverse, choose Restore this batch in the Recent bulk actions list: each profile returns to its earlier published state (reviewed if it was reviewed). On a host without a scheduler run `php bin/wiw.php jobs:run` to make the job run now. Every bulk action and restore is in the Audit log screen.

## Key rotation

Keys to rotate (values live only in the secrets file, mode 600, outside the web root):

| Key | Rotate when | Steps | Owner issue |
|---|---|---|---|
| SMTP credentials | Staff change, suspected leak | Change the password of the cPanel mail account; put the new `SMTP_PASSWORD` in the secrets file; check a test message in the Email log screen shows Sent after `php bin/wiw.php jobs:run`; no deploy needed | M0-EML-01 |
| Signed-link signing key (`SIGNED_LINK_KEY`) | Suspected leak; invalidates outstanding links | Generate 64 random hex characters and replace the value in the secrets file. Every outstanding confirm, unsubscribe and claim link stops working (people ask again), so do it off-peak and tell the editor | M0-PLG-17 |
| Form-token signing key (`FORM_TOKEN_KEY`) | Suspected leak | Generate 64 random hex characters and replace the value in the secrets file. Forms open at that moment must be reloaded; nothing else changes | M0-PLG-18 |
| Backup passphrase (`BACKUP_PASSPHRASE`) | Staff change | Only the pre-migration dumps use it. Keep the old passphrase with the old dumps (they cannot be opened without it) and set the new one before the next deploy | M5 |
| WordPress salts | Suspected session theft; logs everyone out | Fetch new values from `https://api.wordpress.org/secret-key/1.1/salt/` and replace the eight keys and salts in the secrets file. All staff sign in again, so tell them first | M0-INF-11 follow-up |
| Health-check token (`HEALTHZ_TOKEN`) | Suspected leak | Generate a new random value, replace it in the secrets file, update the uptime monitor's address and check `/healthz` with the new token | M5 |

## Moving to a VPS

Only if the live-host checks fail or the site outgrows the shared host. Steps: build a release (`deploy/build-release`); prepare PHP 8.3 with the extensions in the check table, MariaDB and a web server with the same deny rules as the cPanel setup; create the secrets file outside the web root; run `deploy/migrate-live` with `--db-import` of a fresh database dump and `--uploads` of the media folder; run the smoke check; lower the DNS time to live a day before; switch DNS; keep the old host read-only for a week; set a system cron that runs `php bin/wiw.php jobs:run` every minute. Fallback hosting is described in Deployment.

## Performance budget (REQ-041, REQ-042)

Run on a served site: `BASE_URL=http://127.0.0.1:<port> WIW_THEME_READY=1 WIW_PERF=1 npx playwright test` (in `site/`). It loads each page three times cold-cache in Chromium at 412x823 with 4x CPU slowdown, 1.6 Mbps down, 750 kbps up and 150 ms round trip, and takes the median LCP, CLS, script KB and total KB. The numbers it enforces live in `site/tests/e2e/perf/budgets.json`. CI runs it as a non-blocking step of the e2e job.

Baseline on the local PHP built-in server (Windows, no page cache; time to first byte is about 1.1 s there, so LCP here is a worst case, to be re-measured on the staging host):

| Page | LCP ms | CLS | Script KB | Total KB |
|---|---|---|---|---|
| / | 4532 | 0 | 11.3 | 721 |
| /directory/ | 3332 | 0 | 9.9 | 375 |
| /stories/ | 3320 | 0 | 9.9 | 371 |
| /events/ | 3316 | 0 | 9.9 | 371 |
| /contact/ | 2808 | 0 | 9.9 | 372 |

Proposed ceilings (for the owner to agree; docs/theme/theme-acceptance.md still lists the numeric budget as an open point): LCP 6000 ms enforced now with 2500 ms the REQ-041 target reported as a warning, CLS 0.1, scripts 20 KB, page weight 900 KB. Tighten LCP to 2500 ms once measured on the staging host.

## Live-host check results

Filled in at M5 from the checks listed in Deployment (Q87 still open).

| Check | Result | Date |
|---|---|---|
| PHP version and extensions | TODO | |
| Memory limit | TODO | |
| Cron and scheduled jobs | TODO | |
| Inodes and disk | TODO | |
| Outgoing mail limits | TODO | |

## PHP version header (REQ-046, #594)

The guard calls `header_remove('X-Powered-By')` on every response. As a second layer, set `expose_php = Off` in the live host's php.ini (cPanel: MultiPHP INI Editor) and check with `curl -sI https://<site> | grep -i x-powered-by`, which must print nothing. Record the result in the check table above.

## Who receives alerts

The alert contact is the site owner: **TODO(owner): write the name and the email address here before launch.** This file cannot name a person the owner has not given. Alerts that reach them: the uptime monitor, the deadline-breach emails for corrections, removals and data requests, and failed-job notices. Name a second person as cover for when the owner is away.

## Quarterly tasks

- Review installed plugins against the register and apply updates (Updates and plugin policy above).
- Run `composer audit` and `npm audit --audit-level=high` in `site/` and fix or record each finding.
- Rotate or check the keys in the table above that are due; check outgoing mail still passes SPF, DKIM and DMARC.
- Open the Audit log screen and press Verify chain (or run `php bin/wiw.php audit:verify`); copy the audit export off the host.
- Run the smoke check and the performance budget (below) and compare with the last numbers.
- Check who has staff accounts and capabilities; deactivate people who left.

## Audit log protection and export

The audit table has database triggers that refuse UPDATE and DELETE where the database account may create triggers (the Audit log screen shows "Database triggers: on" or "off"). Without them the hash chain still detects tampering: run `php bin/wiw.php audit:verify` (exit code 1 when a row was altered) or press Verify chain on the screen.

A nightly job (`audit.export`) writes the new audit rows to `wp-content/uploads/wiw-private/audit/audit-FIRST-LAST.jsonl`, with a `.sig` file holding an HMAC of its contents. The folder carries a deny-all `.htaccess`; download or sync it off the host (never email it). To restore, import the rows in id order into an empty audit table, then run `audit:verify`; check a copy against its `.sig` with the same site key.

## Page cache (M5-PRF-03, M0-PLG-29; REQ-041, REQ-054, REQ-121, REQ-209, REQ-217)

The page cache is WP Super Cache (pinned through composer, on the plugin allowlist). It is off unless the secrets file has `WIW_PAGE_CACHE=true`, which sets `WP_CACHE` in `wp-config.php`. Its settings are code, not clicks: the plugin's own settings screen is hidden.

- **Turn on:** set `WIW_PAGE_CACHE=true` in the secrets file, then from the site root run `php deploy/cache/install.php`. It copies the plugin's `advanced-cache.php` drop-in and `deploy/cache/wp-cache-config.php` into `wp-content/` and creates `wp-content/cache/`. Running it twice changes nothing. Run it again after every plugin update.
- **Turn off:** remove `WIW_PAGE_CACHE` (or set it to `false`), and delete `wp-content/advanced-cache.php` and `wp-content/cache/`.
- **What is stored:** only an anonymous GET of a named public path (`PageCachePolicy`, filter `wiw_page_cache_public_paths`), answered 200, no query string, not noindex. Never stored: any login, commenter or post-password cookie, previews, form pages, signed-link and confirm pages, the admin, REST, feeds. Lifetime 60 seconds for every page (the ceiling is 120; 60 for lists and search).
- **Unpublish:** the unpublish hook queues a purge job (`PurgeService`) that clears the page from the cache through `wpsc_delete_url_cache`; the origin then answers 404 or 410 and is never cached (non-200).
- **After any plugin, guard or theme change:** rerun the visibility suite (`composer test -- --testsuite Visibility`) and, on a served site with the cache on, request a pending-review profile twice and confirm the `X-Robots-Tag: noindex` header both times.
- **Known limits:** WP Super Cache expires files by garbage collection (every shutdown here), not at serve time, so staleness after an edit is bounded by the unpublish purge and the 60-second GC, not strictly by the lifetime. The guard's per-request Content-Security-Policy nonce is in the page body, so a cached page would carry an old nonce: see the follow-up issue before turning the cache on in production.

## Customer and enquiry CSV export (REQ-077, #324)

The Customers screen has two export buttons (customers, enquiries) for holders of `wiw_export_data` (the Administrator by default). Each export is audited with a row count only. Both files carry the external ID and the change timestamp, so a link to an outside CRM can be added later. A CSV import is not built; when it is, it must match rows by external ID.

## Profile manager role recipe (M3-CLM-14; REQ-253, REQ-261, REQ-244)

No fourth role ships. When a claim is approved, the Administrator creates a limited role once and gives the claimant a staff user with it. The claimant then sees only the My profiles screen and can change only the profiles assigned to them; each change waits as a pending revision until a Reviewer or Administrator other than the author approves it.

### Create the role (once)

1. Sign in as an Administrator. Open Users, then Role editor (the role-editor plugin, Administrator-only).
2. Add a new role named `Profile manager` (slug `wiw_profile_manager`). Do not copy Editor or Reviewer: start empty.
3. Tick exactly these capabilities and no others:
   - `read` (needed to sign in to the dashboard shell)
   - `wiw_edit_assigned_profiles` (opens My profiles and the assigned profiles only)
4. Make sure none of the following are ticked on the role or on the user. Each one lets the person act as staff and would remove the limit, so the role must not have them: `wiw_review_records`, `wiw_review_claims`, `wiw_unpublish_profiles`, `wiw_bulk_unpublish`, `wiw_resolve_duplicates`, `wiw_run_imports`, `wiw_export_data`, `wiw_view_audit`, `wiw_view_suppression`, `wiw_edit_suppression`, `wiw_manage_register`, `wiw_set_sponsored`, `wiw_manage_capabilities`, and every `edit_*`, `publish_*`, `delete_*`, `upload_files` and `moderate_comments` capability of core. A user who holds a staff profile capability as well is no longer limited to assigned profiles.
5. Save. Audit rows: `role.create` (and `role.capabilities` for any later change) with the actor and the granted capabilities.

### Create the claimant and assign the profile (each claim)

1. Users, Add new. Username and email of the claimant; role `Profile manager`. Send the new-user email so they set their own password.
2. Open the profile in the directory editor and, in the Claim and profile manager card, pick the user as the profile manager (it needs `wiw_review_claims`). The profile must be claimed (an approved claim). Audit row: `profile.assign_manager`, with the before and after user, the actor and the reason.
3. The user must enrol a second factor on first sign-in (Two-factor enforcement applies to every user whatever the role). Nothing else opens until they do. Check this yourself by signing in as the user in a private window.
4. The user's own screens: only My profiles and their own account page. A pending revision appears in the Review queue for a Reviewer or Administrator who is not the author. Approve or return it with a note; each decision writes an audit row (`profile.revision_approved` or `profile.revision_rejected`) with actor and reason.

### Checklist for the claim reviewer after approval

- [ ] The claim is approved on the Claims screen (audit row `claim.approve`).
- [ ] A `Profile manager` user exists for the claimant and holds only `read` and `wiw_edit_assigned_profiles` (Role editor, user screen: no extra grants).
- [ ] The profile is assigned to that user (audit row `profile.assign_manager`).
- [ ] The claimant has enrolled a second factor.
- [ ] Signing in as the claimant shows My profiles and nothing else: no Posts, Pages, Media, Users, Tools menus; opening another profile's edit address answers 403.
- [ ] A test change from the claimant appears as a pending revision and the live profile is unchanged; approve or return it from the Review queue as a different user.
- [ ] To end the arrangement, remove the manager from the profile (audit row `profile.assign_manager`, "Manager removed") and deactivate the user.
