# Platform

The module everything else stands on: capabilities and roles, the audit log, settings, the Nominations switch, signed links, mail, scheduled jobs, health checks and cache purge.

## Responsibility

- Define the three default roles and the capabilities they bundle.
- Let the Administrator grant and revoke capabilities without code.
- Write the one append-only, hash-chained audit log.
- Hold settings, including the Nominations switch.
- Issue and consume single-use signed links.
- Send email, queue and run scheduled jobs, check health, purge caches.

## Content types, taxonomies and tables

| Item | Notes |
|---|---|
| `wiw_audit_log` | Append-only: time, actor, action, subject, old and new values, previous hash, hash. Written only by `AuditLogger` |
| `wiw_signed_links` | Token hash (never the token), purpose, subject, email, expiry, used and revoked times |
| `wiw_email_events` | Message id, sent, bounce or complaint, time |
| `wiw_notes` | Internal notes on profiles, customers and nominations. Never public, never shown to anyone scoring |
| Settings (options, autoload off) | The sprint thresholds (the only settings screen), the "import everything as not public" switch (on the Import screen) and the Nominations flag (on the Nominations screen). Response targets and the pending-label wording are fixed in code. Brand values are not here: they live in the theme tokens file |
| Roles | `wiw_reviewer`, `wiw_editor` and the WordPress `administrator` role plus every `wiw_*` capability |

## Admin screens

| Screen | What it does |
|---|---|
| Setup | `wiw_run_setup`, Administrator only (fixed). The eleven-line checklist with one-click fixes (the staff display names line is advice only); the Turnstile site key and secret key form (the secret is never shown again; constants win); the guarded **Update the database** button that applies pending migrations on a live site after an "I have a backup" tick (D-070); the demo content chooser. The demo imports the plugin's `sample/sample-50.zip` when a release was packaged with `SAMPLE_BUNDLE=<path to sample-50.zip> bash site/bin/package-release.sh`, else invented profiles; demo awards and home page pins always use invented profiles |
| Site settings > Required plugins | `wiw_run_setup` plus core `install_plugins` (Install) and `activate_plugins` (Activate). Status, Install and Activate for FluentSMTP and wpDiscuz (required) and Two Factor (recommended). A dismissible notice reminds Administrators while one is missing |
| Dashboard | Differs by role. Reviewers see review and directory tiles. Editors add support and nominations counts. Administrators add sprint, revenue and arrangement tiles |
| Audit log | Read and verify the chain |
| Sprint thresholds | The two sprint targets. Administrator only, audited. Shown only while the `sprint_tracking` theme setting is on (REQ-272) |
| Roles, per-user grants and exports | Provided by WordPress and a role-editor plugin, not drawn in the prototype; the module registers the capabilities, audits grants and checks `wiw_export_data` |
| Nominations switch | On or off, with an optional reason; changed from the Nominations screen |
| Email log | Every message sent, with status |
| Purge log | Cache purges and unpublish events |
| Exports | CSV exports of sponsored story deals, nominations and personal data. Each is logged |
| Users, plugins, themes | Native WordPress. Not custom |

## Capabilities

| Capability | Reviewer | Editor | Administrator |
|---|---|---|---|
| `read` (core) | yes | yes | yes |
| `wiw_view_audit` | no | no | yes |
| `wiw_manage_capabilities` (fixed) | no | no | yes |
| `wiw_toggle_nominations` (fixed) | no | no | yes |
| `wiw_export_data` | no | no | yes |
| `wiw_run_setup` (fixed) | no | no | yes |
| `manage_options` (core, fixed) | no | no | yes |
| User, plugin and theme capabilities (core, fixed) | no | no | yes |

- WordPress's built-in subscriber, contributor, author and editor roles are removed on activation. The default role is empty and registration is closed.
- Capabilities that cannot be granted to a non-Administrator are enforced by a `user_has_cap` filter. The Administrator may create more roles and grant capabilities to them.
- Every grant, revoke and use of a sensitive capability is audited.

## Public read models and endpoints

| Item | Behaviour |
|---|---|
| `GET /link/{token}`, `POST /link/{token}` | A GET shows the page. Consuming the link needs a POST, so email scanners cannot burn it. A used or expired link shows "expired or already used" |
| `GET /healthz` | Database, job heartbeat and disk. No detail unless a token is sent |
| No public JSON API | Core REST is locked to staff (guard) |

## Jobs, hooks and events

| Job | Schedule |
|---|---|
| Cache purge | Queued after commit, high priority |
| Email | Queued sends, retries, event log |
| Import chunks | Per step |
| Claim and request reminders | Daily, with day-3 and day-4 triggers for claims |
| Sponsored label apply and expire | Scheduled |
| Retention, backup, view-count roll-up, curation seed refresh | Scheduled |

Jobs run on Action Scheduler through WP-Cron, with a time budget. A real cron on the host is an optional improvement.

## Business rules

- **Deny by default.** Every admin screen and action checks a named capability. A screen hidden from a menu is also blocked by direct URL.
- **Audit everything sensitive.** Staff actions on profiles, labels, sponsored story deals, imports, bulk unpublish, suppression, the Nominations flag and capability grants go through `AuditLogger`.
- **Signed links.** 32 random bytes, stored only as a hash, bound to one purpose and record, expiring (14 days by default, shorter for verification), single use, no personal data in the URL. Staff can revoke or reissue.
- **Mail.** FluentSMTP is recommended (installed from Site settings > Required plugins and connected to the host's mail account, with SPF, DKIM and DMARC). When it is active the plugin leaves sending to it; otherwise the `WIW_SMTP_*` constants if set, else plain `wp_mail`. FluentSMTP's own email log is forced off, because message bodies carry signed links. Every message is logged in our email log. Marketing messages carry a one-click unsubscribe.
- **Two-factor.** Available and recommended for staff, not enforced (D-070). The Setup screen shows who has it on.
- **Imports on a live site.** Bundles up to 512 MB upload in pieces and are applied in short steps; larger ones go in the server import folder `wp-content/uploads/wiw-import/` and are picked on the Import screen.
- **No PII in logs or errors.** Logs carry identifiers only.
- **The only feature flag is Nominations.**
- **Cache.** Public HTML is cached at the edge for at most 120 seconds (60 for lists and search). If Cloudflare is added, unpublish triggers a purge by URL.

## Route outline

| Area | Routes |
|---|---|
| Public pages | `/`, `/about`, `/people`, `/businesses`, `/institutions`, `/industries`, `/stories`, `/awards`, `/profile/{slug}`, `/search`, `/sitemap.xml`, `/robots.txt`, legal pages |
| Public forms | `POST /enquire/{slug}`, `/correct/{slug}`, `/remove/{slug}`, `/report/{slug}`, `/claim/{slug}`, `/subscribe`, `/contact`, `/nominate`, `/newsletter`, `/data-rights` |
| Signed links | `/link/{token}` |
| Staff | `/wp-login.php` (two-factor when the person switched it on), then wp-admin |
| Health | `/healthz` |

## Tests that must exist

- `test_REQ_051_audit_log_chain_detects_tampering`
- `test_REQ_244_capability_grant_and_revoke_are_audited`
- `test_REQ_235_fixed_capability_cannot_be_granted_to_non_administrator`
- `test_REQ_229_registration_is_closed_and_default_role_is_empty`
- `test_REQ_233_link_expires_and_cannot_be_reused`
- Authorisation matrix: every role and capability pairing returns 403 or 200 as expected, with a granted and a revoked case.
- Architecture test: only `AuditLogger` writes the audit table.

## Depends on

Nothing in the product modules. Every module depends on it.

## Requirement ids covered

REQ-047, 051, 075, 081, 083, 087, 094, 108, 109, 122, 188, 194, 204, 205, 209, 228, 229, 233, 235, 239, 240, 244, 245.
