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
- **Mail.** `wp_mail` through the host's SMTP, with SPF, DKIM and DMARC. Every message is logged. Marketing messages carry a one-click unsubscribe.
- **No PII in logs or errors.** Logs carry identifiers only.
- **The only feature flag is Nominations.**
- **Cache.** Public HTML is cached at the edge for at most 120 seconds (60 for lists and search). If Cloudflare is added, unpublish triggers a purge by URL.

## Route outline

| Area | Routes |
|---|---|
| Public pages | `/`, `/about`, `/people`, `/businesses`, `/institutions`, `/industries`, `/stories`, `/awards`, `/profile/{slug}`, `/search`, `/sitemap.xml`, `/robots.txt`, legal pages |
| Public forms | `POST /enquire/{slug}`, `/correct/{slug}`, `/remove/{slug}`, `/report/{slug}`, `/claim/{slug}`, `/subscribe`, `/contact`, `/nominate`, `/newsletter`, `/data-rights` |
| Signed links | `/link/{token}` |
| Staff | `/wp-login.php` with two-factor, then wp-admin |
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
