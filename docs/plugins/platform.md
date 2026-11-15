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
- **Two-factor.** Available and recommended for staff, not enforced (D-070). The Setup screen shows who has it on. Staff without it get a reminder (`Platform\TwoFactor\Reminder`, issue 814): a warning notice on every admin screen and a Dashboard box, both with "Turn it on now" (their own `profile.php#two-factor-options`); "Remind me tomorrow" hides the notice for 24 hours for that user (user meta `wiw_two_factor_remind_after`, nonce, core `read` only, changes only the current user); and one email, the `two_factor_reminder` event through `Notifier`, at the first sign-in without it (user meta `wiw_two_factor_reminder_emailed`; held, not marked, when the hourly mail cap is reached). Staff means the Administrator and the plugin roles, or any user holding a `wiw_*` capability (`StaffStatus::is_staff`). It stops as soon as a method other than recovery codes is on, never blocks, and shows nothing without the Two Factor plugin. In test mode only, seeded browser-test users marked `wiw_e2e_quiet` get no reminder.
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

## Staff UI kit (REQ-275)

Shared building blocks for staff screens, in `src/Platform/Admin/Ui/`. Every staff table and form uses them (phase 1: the Review queue, Profiles, Edit profile and Add profile; phase 2: every other staff screen).

**List tables.** `ListTable` extends `WP_List_Table`. A subclass needs a no-argument constructor (or overrides `make()`) and declares:

| Method | What it returns |
|---|---|
| `bulk_action()` (static) | The admin-post action the bulk form posts to, for example `wiw_review_bulk`; also the nonce action |
| `capability()` (static) | The screen capability, checked by ActionGuard before any bulk action |
| `screen_slug()` (static) | The screen's page slug, where the user is sent back |
| `columns()` | Column key to heading; the base adds the checkbox column |
| `primary_column()` | The column that carries the row actions |
| `fetch( $page, $per_page )` | `items` and `total` for one page (20 a page by default; override `per_page()`) |
| `item_id( $item )` | The id the checkbox carries; 0 for a row bulk actions do not apply to |
| `item_name( $item )` | Plain text name, for the checkbox label and the confirmation list (`name_for_id()` reads the post title by default) |
| `column_html( $item, $column )` | One escaped cell; use `ListTable::pill( $text, $tone )` for status pills |
| `row_actions_for( $item )` | Optional. Key to `label`, `url`, `capability`, an optional `confirm` question and an optional `destructive` flag (the link is shown in red). Avoid the keys `approve` and `unapprove`: core CSS hides them |
| `bulk_action_definitions()` | Optional. Key to `label`, `capability`, `handler` (`callable( int $id, Request $request ): bool\|WP_Error`), and optional `destructive` (red in the dropdown and on Apply, and confirmed), `done` (for example `%d approved`) and `fields` (`callable(): string`, extra inputs for the confirmation step) |
| `screen_args( $request )` | Optional. Query args kept when coming back and when paging (filters, page) |
| `read_only()` | Optional, default false. True prints no bulk form, no checkboxes and no bulk actions: the table sits in a GET form to its own screen that keeps the filters. Use it for logs and other lists with nothing to do in bulk |
| `pager_on_one_page()` | Optional, default true. False drops the pager when everything fits on one page, so a screen with several tables (or a short embedded list) does not repeat the pager's ids `table-paging` and `current-page-selector` |

Register the handler on `init` in the admin with `MyTable::register_bulk_handler()` and print the table with `( new MyTable() )->html( $request )` inside a `wrap wiw-staff` div. The base checks the nonce and the action's capability, reads ids only through `Request`, runs the handler once per id and shows one notice. A destructive action asks in the browser and is confirmed on the server (`confirm=1`); an action with `fields` always goes through the confirmation step. `step_url( $key, array( $id ) )` gives a row action that opens that step for one row. Paging always moves by GET to the list's own screen with `screen_args()` kept: the pager links are built from them, and the page box belongs to a small GET form after the bulk form (through the `form` attribute), so pressing Enter in it opens that page and never posts the bulk form. This needs no JavaScript. At 782 px and below the primary column is shown first whatever its place, and the other cells open under it, so a table may put its primary column anywhere. A handler must call the same domain service as the single action, so the service checks the capability per item and writes the audit row; a bulk action never adds a power the screen did not have.

**Forms.** `Form` prints fields: `text`, `email`, `url`, `textarea` (with `counter` and `maxlength`, kept live by `char-count.js`), `select`, `searchable_select`, `checkbox`, `toggle` (a switch), `radio_cards`, `row` (around a control the screen built), `section` (a card), `actions` (with `sticky`) and `open` (with `unsaved` for the leave-page warning). Common arguments: `id` (default `wiw-f-{name}`), `help`, `error`, `required`, `class`, `attrs`. An error adds `aria-invalid` and links the error text with `aria-describedby`. `open` also takes `method`, `action`, `enctype` (`multipart/form-data` for an upload) and `attrs` (extra attributes on the form tag, for example `data-personal-data`). `section` puts its `id` on the `<section>`, so `#id` links land on the card, and gives the heading `{id}-title`, which names the section. Names, values and validators stay with the screen.

**Styles.** `StaffAssets` loads `assets/admin/ui/staff.css` and `staff.js` on WHOISWHO screens only (hook suffix `_page_wiw…`). Styles apply inside `.wiw-staff` and `.wiw-list-table`, use WordPress admin colours (`--wp-admin-theme-color`) and no brand values. The kit styles text, email, url, password, number, search and select fields in `.wiw-field`, and text, search, number, date and select filters in `.wiw-filters`, so screens need no field styles of their own.

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

REQ-047, 051, 075, 081, 083, 087, 094, 108, 109, 122, 188, 194, 204, 205, 209, 228, 229, 233, 235, 239, 240, 244, 245, 275.
