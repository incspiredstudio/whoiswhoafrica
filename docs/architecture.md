# WHOISWHO — Architecture (WordPress)

Status: **Proposed v2 for WordPress, awaiting your approval (Gate 2).** Written 2026-11-08; revised 2026-11-09 for a local WordPress build with cPanel SMTP and no object storage (D-049 to D-051). It replaces the Laravel + Filament draft of 2026-11-06 (D-031). Inputs: `docs/requirements.md` (REQ-001 to REQ-244, withdrawn rows ignored), `docs/decisions.md` (D-001 to D-046), `docs/contracts/import-format.md` (v1.0-draft). No application code exists yet. Anything I could not support is marked **verify**.

Assumed constraints (edit if wrong): one human supervisor plus Claude Code agents; low budget; Nigeria first; **no online payment processing** (D-005); easy managed deployment; wiwi.africa and a cPanel account already exist (D-020); speed first (D-026). **The site is built locally on WordPress and moved to the live cPanel host when finished (D-050).** Legal is skipped for now (D-051): items that depend on Legal are marked deferred and do not hold up the build.

**What the site is not (so the design can stay small):** no public accounts of any kind (D-034), no claiming (D-036), no judge portal (D-019, D-034), no source registry or approval (D-038, D-042), no AI drafting (D-037, REQ-165), no kill switch (D-043), no tiers (D-028), no payment code (D-005). Acquisition is a separate pipeline that never publishes and talks to the site only through `docs/contracts/import-format.md` (D-033 as amended).

---

## 1. Summary

WordPress, built and tested locally and then migrated to the existing **cPanel** account (Cloudflare in front is a go-live option, not a dependency), with the product-specific rules in **one custom plugin (`whoiswho-core`), one must-use guard plugin (`whoiswho-guard`) and one theme (`whoiswho`)**. The database is the MariaDB/MySQL that cPanel offers; WordPress's own tables hold content and users, and **custom tables hold everything WordPress does poorly**: publication state, per-field provenance, versions, the commercial register, awards scores, signed links and the audit log. No Node server, message broker, Redis, payment gateway or front-end framework is needed.

Why this shape:
- You decided on WordPress (D-031) for editor familiarity and hosting fit. The comparison in section 13 recorded WordPress as the weakest on exactly the rules that matter here (nothing public before the rules allow it, across every surface; instant 410; provenance). So the design **does not trust WordPress's defaults for visibility**. It makes three moves instead:
  1. **A state machine owns visibility.** A profile has one `pub_state` in a custom table. Only the `StateMachine` service changes it. A `wp_insert_post_data` guard refuses any other change to `post_status` on a profile, so a plugin, quick-edit or REST call cannot publish by accident (REQ-208, 210).
  2. **A must-use plugin closes the leaking surfaces** (REST, feeds, core sitemaps, oEmbed, author and attachment pages, previews, search) and emits all SEO markup itself, with **no SEO plugin**, so nothing can generate a sitemap, feed or schema entry for a profile that is not public (REQ-217).
  3. **Public templates read only through read models** (`PublicProfile`, `PublicStory`), never through `get_post_meta` or `WP_Query` on the profile type.
- The two hardest requirements are solved by design, not by a plugin: **visibility (REQ-208, 210, 216 to 218)** and **"unpublish immediately, even on indexed pages" (REQ-209)**.
- No front-end accounts means a small attack surface: the only logins are staff in wp-admin, behind two-factor authentication.

| Layer | Choice |
|---|---|
| CMS | WordPress (current stable; WordPress's requirements page read 2026-11-06 lists PHP 8.3 recommended, MariaDB 10.11+ or MySQL 8.0+; **verify at build**) |
| Product logic | Custom plugin `whoiswho-core` (modules in section 3.1) and must-use plugin `whoiswho-guard` |
| Theme | Custom theme `whoiswho`: server-rendered PHP templates, design tokens in one file (`theme.json` plus a tokens file), no page builder |
| Admin | wp-admin with custom roles and capabilities (Reviewer, Editor, Administrator), custom list tables and screens; no judge area |
| Database | MariaDB or MySQL (whatever cPanel offers), InnoDB, utf8mb4, through `$wpdb`; custom tables for product data |
| Auth | WordPress users for staff only; enforced two-factor; front-end registration closed; no application passwords |
| Hosting | Built locally; live on the existing cPanel account. Cloudflare in front is optional at go-live. Fallback: one small VPS |
| Payments | **None.** The register stores method and reference text only |
| Email | cPanel mail SMTP through `wp_mail`; SPF, DKIM and DMARC on wiwi.africa. Brevo is an optional later upgrade |
| Storage | WordPress media library on the host (originals kept); private files outside the web root; backups from cPanel plus a plugin or export if needed. No object storage |
| Search | `wiw_profile_search` table with InnoDB FULLTEXT, built from public states only |
| Jobs | WP-Cron with Action Scheduler; a real cron every minute is an optional improvement on the live host |
| AI | **Not on the site.** The acquisition pipeline owns drafting and basic verification |
| Observability | Error log with IDs only; uptime monitor, Sentry and Cloudflare Web Analytics are later, optional |
| CI/CD | GitHub Actions run tests and checks. Going live is a migration of the finished local site (section 8); automated deploys can come later |

---

## 2. Stack per layer

| Layer | Choice | Why it fits | Rejected | Lock-in / scaling risk |
|---|---|---|---|---|
| **CMS and framework** | WordPress, PHP, custom plugin + theme (ADR-001) | Your decision (D-031). Native to cPanel. Editors already know it. Posts, taxonomies, media, revisions and the admin shell come free; the product rules are custom code in a plugin that agents can test. | Laravel + Filament (the earlier draft: strongest on the gating rules, but not the decision). Headless CMS options (second codebase, Node host). | Medium. WordPress is open source (GPL) and widely hosted. Product logic sits in plain classes under `src/`, so it is not tied to WordPress templates. |
| **Public frontend** | Server-rendered PHP templates; CSS variables from one tokens file; small JS only where needed (ADR-001) | Indexable (REQ-044), fast on 4G (REQ-041), 360 px (REQ-040). All brand values from the tokens file (REQ-052, 190). | Page builders and block-heavy themes (large surface, uncontrolled output). SPA front end. | Low. |
| **Admin console** | wp-admin: custom roles and capabilities, `WP_List_Table` screens, a few custom pages (ADR-004) | Review queue with field-level diff and source beside each claim, Support inbox, register, nominations and score entry, import, audit log, read-only Sources, users and capabilities. No third-party admin framework. | Filament (Laravel only). A separate admin app (second codebase). | Low-medium: admin screens are WordPress-specific; domain services are not. |
| **Database** | MariaDB/MySQL through `$wpdb`; custom tables created and versioned by the plugin (ADR-002) | Native to cPanel. 10k to 100k profiles with FULLTEXT is within range (REQ-079). Money as integer kobo (REQ-112). | PostgreSQL (not on cPanel). Post meta for everything (cannot be queried or constrained; contract unknown U1). | Low. Standard SQL, no vendor-specific features. |
| **Auth** | Staff users only; enforced two-factor; closed registration; lockout; no application passwords (ADR-004) | REQ-047, 108, 109, 229. No visitor or owner login exists, so there is nothing to attack at the front. | Third-party identity (extra processor). Front-end login (rejected by D-034). | Low. |
| **Hosting** | Local build, then the existing cPanel account; fallback VPS (ADR-003) | Zero extra cost. Host limits are checked on the live host at import time (section 8). Cloudflare, if added at go-live, gives CDN (REQ-042), WAF and rate limits (REQ-046, 122), bot challenge (REQ-081) and cache purge (REQ-209). | Managed WordPress hosts (new monthly bill, held as fallback). | Medium: shared hosting is the weakest link for uptime (REQ-045) and cannot run daemons (ADR-011). |
| **Payments** | None (D-005). | The register stores method and reference text; a digit-pattern guard rejects card-like numbers. | — | — |
| **Email** | cPanel mail SMTP through `wp_mail`; SPF, DKIM, DMARC on wiwi.africa (ADR-008) | No new account. One-click unsubscribe on opt-in mail still applies (REQ-061, 075). Bulk nominee mail volume is a risk to check at go-live (hosts cap messages per hour). | Brevo, SES, Postmark: later, optional. | Low: standard SMTP. |
| **File storage** | WordPress media library for public images; private uploads outside the web root (ADR-007) | REQ-080 (amended), 127, 203. No claim documents exist any more; private files are customer intake uploads and removal-request evidence. | Object storage and CDN offload (not needed now). | Low. |
| **Search** | FULLTEXT on a flattened table built from public read models (ADR-010) | REQ-007, 008, 079. | External search services (cost, operations). WordPress's built-in search (leak risk, poor ranking). | Upgrade path: swap the search service behind one interface. |
| **Jobs** | WP-Cron with Action Scheduler and a time budget (ADR-011) | WordPress already schedules jobs on page requests; a real cron on the live host is an optional improvement if edge caching makes requests rare. Jobs: purge, email, imports in chunks, retention, stale checks, sponsored expiry, backups. | WP-Cron alone; Redis workers. | Move to a supervised worker on a VPS with no code change. |
| **AI** | None on the site (ADR-009 superseded) | REQ-165: AI drafting is in the acquisition pipeline. The site only verifies hashes and field rules on import. | — | — |
| **Observability** | Structured logs with IDs only; first-party counters (REQ-078); an uptime monitor on a health URL (REQ-045, 050) and Cloudflare Web Analytics are later, optional | Cookieless, so no consent banner for analytics (REQ-062). | GA4 (cookies, cross-border). | Low. |
| **CI/CD** | GitHub Actions (ADR-012) | Matches the repo and PR workflow in CLAUDE.md. | Manual FTP. | Low. |
| **Bot / abuse** | Per-IP and per-email/token throttles in the plugin; Cloudflare Turnstile and WAF rules when Cloudflare is added | REQ-081, 122. | reCAPTCHA (Google cookies). | Low. |

---

## 3. System design and key flows

### 3.1 Overview

```
 Visitor ──► (optional: Cloudflare) ──► cPanel host
                                                        ├─ WordPress core (pinned, vendored in the release)
                                                        │    ├─ mu-plugin  whoiswho-guard   (visibility, REST lock, headers, hardening)
                                                        │    ├─ plugin     whoiswho-core    (modules below)
                                                        │    └─ theme      whoiswho         (templates, tokens)
                                                        ├─ WP-Cron / Action Scheduler: job runner
                                                        └─ MariaDB/MySQL (WordPress tables + wiw_* tables)
 whoiswho-core ──► cPanel SMTP (email)  ──► (optional) Cloudflare API (cache purge)
 Acquisition pipeline ──(export bundle, files, contract section 2 to 12)──► Administrator uploads in wp-admin
 whoiswho-core ──(suppression snapshot, import result)──► acquisition side
```

**Modules** (namespaces under `whoiswho-core/src/`), each owning its tables and a service API:

| Module | Owns |
|---|---|
| `Directory` | profile post type and fields, taxonomies, search, read models (`PublicProfile`), profile versions, sources view |
| `Review` | review queue, duplicates, `StateMachine`, `PublishService`, stale records, bulk unpublish |
| `Import` | bundle validation, dry run, apply in chunks, undo, hash check, import results, suppression snapshot export |
| `Trust` | Verified and Featured labels, checklist, Sponsored link application (writes only its own columns) |
| `Commerce` | leads, customers, arrangements, sponsors, counting rule, dashboard figures |
| `Content` | stories, pages, related-content links |
| `Awards` | editions, categories, criteria, nominations, stages, panel names, conflicts, scores, results |
| `Compliance` | support inbox (typed items), data requests, suppression list, consents, retention, processors |
| `Platform` | capabilities and roles, audit logger, settings, the Nominations flag, signed links, health, cache purge |

**Boundary rules**, enforced in CI by architecture tests (section 7):
- `Awards` may not reference `Commerce` (REQ-199, 037). No code path or join leads from commercial data to nominations, screening, shortlist, score or result screens.
- `Commerce` and `Trust\SponsoredLabel` may not write `Verified` or `Featured` (REQ-184).
- Only `Review\StateMachine` (called by `PublishService`, `BulkUnpublish`, `Import` and `Compliance\Removal`) writes `pub_state`; only it may let a profile's `post_status` become `publish`.
- Public templates read profiles and stories only through `PublicProfile` and `PublicStory`.
- Only `Platform\AuditLogger` writes `wiw_audit_log`.

### 3.2 Flow F1: publication states and visibility (REQ-208, 210, 214, 216 to 218, 226)

**One state, stored in one place.** `wiw_profile_state.pub_state` is one of the six states of REQ-210: *Imported – not public*, *Published – review pending*, *Published – reviewed*, *Unpublished*, *Rejected*, *Removed and suppressed*. It is separate from the trust label (REQ-150: none, Verified or Featured) and from Sponsored (REQ-155).

**Mapping to WordPress.** Only the two Published states give the post `post_status = publish`. Every other state uses a non-public custom status (`wiw_hidden`, registered with `public=false`), so core WordPress surfaces never treat it as public. The `StateMachine` sets both fields in one transaction. A `wp_insert_post_data` filter refuses any write that changes `post_status` or `pub_state` outside the `StateMachine` (REQ-210: "any other attempt is refused and logged").

**Who moves what** (REQ-210 table, section 12.2 of the requirements):

| Transition | Actor and capability |
|---|---|
| (import) → Published – review pending or Imported – not public | Administrator running the import (`wiw_run_imports`); the state follows the contract's section 9 rules |
| Imported or Published – pending → Published – reviewed, or Rejected | Reviewer, Editor or Administrator with `wiw_review_records`; the approving reviewer must not be the importer (REQ-148) |
| Any public state → Unpublished | `wiw_unpublish_profiles` (single) or `wiw_bulk_unpublish` (filter); restore returns the previous state |
| Any state → Removed and suppressed | removal flow (F2), which also writes the suppression list |

**Public read models.** `PublicProfile::find( $slug )` returns a data object only when the state is one of the two Published states, and exposes only allowlisted fields (REQ-139 for individuals). The pending label (REQ-216), the `X-Robots-Tag: noindex` header and robots meta (REQ-217), and "Reviewed on <date>" are produced by the same class, so the theme cannot forget them. Listing pages (home, discover, industry) query `wiw_profile_search` with `reviewed = 1`; the on-site search and an "include profiles pending review" view include pending profiles, always with the label (REQ-218). Individuals and entrepreneurs are never public before approval (REQ-208), so they appear in no state except *Published – reviewed*.

**Customer-submitted and complimentary profiles.** They arrive in *Imported – not public* with `origin = customer`, shown in the review queue as "submitted", and become public only when a human approves (REQ-014). REQ-210 has no state named for this; I reuse *Imported – not public* and flag it as Q73.

**Edits.** A staff member edits a profile directly; the edit goes live on that person's sign-off (accepted, D-040), is written to `wiw_profile_versions` (author, time, reason, old and new values; REQ-125) and to the audit log. A correction request is a Support inbox item that a staff member acts on (F6); it creates no draft and no second approver. Re-import never overwrites a reviewed field (REQ-161, 221): the importer flags the change on the review queue instead.

**Tests (REQ-208, 217).** A matrix test builds one profile per state and requests it on every surface: profile page, directory and industry lists, search, `sitemap.xml`, core sitemaps, feeds, REST, oEmbed, `?p=ID` and `?page_id=ID` shortlinks, attachment pages, author archives, previews, share-image URL and cache headers. It asserts the response, the `X-Robots-Tag` and the label for each state against REQ-210 section 12.1.

### 3.3 Flow F2: immediate unpublish, 410, bulk unpublish and removal (REQ-209, 193, 147, 207, 158, 091, 226)

Triggers: a single unpublish (`wiw_unpublish_profiles`); **bulk unpublish by filter** (`wiw_bulk_unpublish`: Administrator and Reviewer by default, D-045; the Administrator can grant it, REQ-244); a valid removal request; a nominee declining; adding to the suppression list.

1. **Bulk unpublish** (replaces the kill switch, D-043). The screen filters by source, type, import batch and state, in any combination, and shows a live match count. A mandatory reason and a confirmation that shows the count and the first few names are required. One request creates a `wiw_bulk_actions` row and schedules chunked jobs (a few hundred profiles per job) so a large batch stays within the host's limits.
2. For each profile, in one transaction: `StateMachine` sets *Unpublished* (or *Removed and suppressed*), the search row is deleted, a suppression row is added if the trigger is a removal or decline, and **one audit row per profile plus one for the batch** is written (REQ-193).
3. The origin returns **410 Gone with `X-Robots-Tag: noindex`** from the next request. It reads the state from the database, not from a cache, through a `parse_request` handler that resolves a slug to a tombstone in `wiw_profile_state`. Restoring a reversible unpublish returns 200 again. Rejected and Imported – not public return 404.
4. If Cloudflare is in front, then after commit **high-priority purge jobs** call the Cloudflare API (without it there is no edge cache and the 410 in step 3 is already the live answer): purge by URL for the profile page, its list pages, the sitemap and the share-image URL, in batches (the per-call limit is **verify**); a bulk action over a threshold also purges the HTML cache broadly. The edge caches public HTML for at most **120 seconds** (60 seconds for lists and search), so the worst case after a failed purge is two minutes. Failed purges retry and alert.
5. `sitemap.xml` is generated from the read model on request (cached five minutes) and purged by the same job (REQ-121, 209).
6. **Restore this batch** reverses one bulk action by its ID, only for profiles still unpublished by that action; it is audited the same way.
7. **Removal request (REQ-158).** A correction, removal or report arrives as a typed Support inbox item (F6) with a due date. A valid removal from the subject is acted on within two business days: staff unpublish the profile and, if valid, the suppression list gains the person or organisation (REQ-147, 207). Only the Administrator edits the suppression list manually; a removal handled by an Editor adds the entry automatically through the service, and nobody below the Administrator can undo it. Imports re-check the live list (contract section 11, check 3).
8. N21 (human): file search-engine removal requests for already-indexed URLs.

Check on the live host at import time: confirm 410 and, if Cloudflare is used, purge by URL, and record the time to 410.

### 3.4 Flow F3: import from the acquisition pipeline (REQ-237, 148, 212, 214, 221, 222; contract sections 2 to 12)

The pipeline never publishes and holds no WordPress credential (D-033 as amended, REQ-211). A named **Administrator** (`wiw_run_imports`, D-044) starts every import.

1. **Transfer.** The export bundle (manifest plus `records.jsonl`, JSON Lines) is uploaded over HTTPS in wp-admin, or placed in a private directory outside the web root and run with a CLI command (`php bin/wiw.php import`, or WP-CLI if the host has it, so WP-CLI is not required). Files are encrypted at rest, logged, and deleted after a retention period (contract U9). A REST import route is **not built for now** (Q67 default): it would need a service account, a fourth role that REQ-083 does not allow.
2. **Manifest check.** Known contract version, counts, and `records_sha256` over the file. A bad manifest refuses the whole bundle.
3. **Dry run (REQ-237).** Every record is validated without writing: schema and allowlisted fields per entity type, per-field provenance present, no low-confidence or unsourced field (REQ-143), verification report with the required results (REQ-214), **recomputed content hash equal to `content_hash`** (RFC 8785 canonical JSON; REQ-221), suppression check against the **live** list (REQ-207), images empty (contract section 10), taxonomy terms known. The screen shows a diff and the result code for each record. Unknown taxonomy terms are listed for mapping: an Editor or Administrator creates or maps the terms, then the dry run is repeated.
4. **Apply in chunks.** After the Administrator confirms, an authenticated, resumable job runner applies 25 to 50 records per step (tuned on the live host at import time) from the admin page or the CLI. Progress is stored per batch, so a time-out resumes where it stopped. Each record gets a result code: `CREATED`, `UNCHANGED`, `UPDATED`, `FLAGGED_CHANGE` or a refusal code. A record failing a check is refused and the rest continue.
5. **State.** WordPress sets the state by the contract's section 9 rules, in order: the *import everything as not public* setting; a probable duplicate; an individual or entrepreneur; an organisation (live, labelled "Compiled automatically from public sources. Human review pending.", noindex). The setting is an Administrator-only option, stored in settings and audited.
6. **Storage.** Fields are stored as post meta registered from the entity-type allowlist (never exposed through REST); **per-field provenance** goes to `wiw_field_provenance`; claims, passages and the verification report go to `wiw_claims`, `wiw_passages` and `wiw_verification` (admin-only, never public, retention per REQ-063). Source, batch ID, tier, record ID and content hash go into `wiw_profile_state`, which makes source and batch filters cheap (REQ-242).
7. **Undo a batch** (REQ-237): records created by the batch that are still unreviewed are removed; unreviewed updates revert to the previous version; reviewed records are kept (contract U14).
8. **Result and audit.** An import result (counts by code, `[record_id, code]` per record, the role of the person, no field values) is written for the acquisition side. The audit log records the person, batch, file hash, counts and states set (REQ-228). The dashboard counts imported, pending review, reviewed and published (REQ-239).
9. **Review.** The WordPress queue replaces review packs. Each claim is shown beside its source and retrieval date, with the verification report, read-only (REQ-084, 241). Approve sets *Published – reviewed*, removes noindex, adds the sitemap and schema entries and purges the cache in one flow; reject makes it not public (410 if it was live) (REQ-222).

**Contract unknowns (section 14 of the contract), proposed answers.** Not edited in the contract; for your ruling with this document:

| # | Proposed answer |
|---|---|
| U1 | Profile post type `wiw_profile`; provenance, claims, passages and reports in custom tables (not post meta) |
| U2 | JSON Lines file upload in wp-admin or by CLI; REST deferred; REQ-237's "CSV or REST" wording should say "file or REST" (Q77) |
| U3 | Chunked, resumable apply with per-record results (step 4); chunk size tuned on the live host at import time |
| U4 | Q64 stays open. Recommended: one site, with *import everything as not public* on until the safeguards in the plan pass |
| U5 | The site owns the term values (taxonomies); unknown terms go through a mapping step before apply |
| U6 | The site generates slugs from the name with a collision suffix; `record_id` maps to the post ID in `wiw_profile_state` |
| U7 | `current_organisation_ref` resolves at read time and is shown only if that organisation is public |
| U8 | The approved source list stays in the pipeline (D-038, D-042). The site holds a read-only snapshot used by the Sources screen. Needs a contract addition (Q74) |
| U9 | Manual upload by a named Administrator over HTTPS; files encrypted at rest and deleted after a retention period |
| U10 | An AI summary is hidden before review for individuals and entrepreneurs; for organisations it is a setting (default hidden) until Q46 is decided |
| U11 | Passages in an admin-only table, kept for the review period, then deleted or reduced to hashes per REQ-063 |
| U12 | No images now (Q34); the media pipeline in ADR-007 would be reused |
| U13 | The site cannot check age (REQ-140). The reviewer checklist includes a possible-minor check; REQ-068 stays a seed-rule and checklist control |
| U14 | As step 7 |

### 3.5 Flow F4: commercial register and the counting rule (REQ-021 to 023, 179 to 184, 199, D-045)

- `wiw_arrangements` store amount in integer kobo plus ISO currency, method and reference text, date received, `confirmed_by` (an Administrator user), start and end dates, complimentary flag, notes and an external ID. Only the **Administrator** (`wiw_manage_register`) can open the register, confirm a payment or see the sprint and revenue tiles (D-044, D-045). Finance is the offline business function, not a role.
- **Status is computed, not typed** (REQ-022): `ArrangementState::for( $row )` derives *Confirmed* only if amount, currency, method, reference, date received, `confirmed_by` and onboarding-started are all present and the row is not complimentary or cancelled. The dashboard counts Confirmed and paid. Property tests cover the rule (complimentary never counts; removing a required field flips the state).
- The thresholds (10 subscriptions, NGN 5,000,000) are settings; the dashboard shows both plus revenue per stream. Each arrangement type has an editable default stream (REQ-180, 181).
- **Sponsored label.** It is set from an arrangement link or by hand and applied between the start and end dates by a scheduled job (REQ-116, 182). It writes only the `sponsored_*` columns and cannot touch `trust`; an architecture test asserts this (REQ-184). A pending profile cannot be Verified, Featured or Sponsored or linked to an arrangement (REQ-226); the service refuses.
- Leads (`wiw_leads`, `wiw_customers`) are managed by Editors (REQ-178). Lead conversion creates a proposed arrangement that only the Administrator can open (a gap listed in the consistency report). CSV export is Administrator-only and logged (REQ-069, 077, 087).

### 3.6 Flow F5: nominations and offline judging (REQ-194 to 202, 234, 036 to 038)

*Judging detail is deferred: to revisit later (the user will return to it). Nominations and score entry below are the current default.*

- The only flag is **Nominations** (REQ-194, D-037): an option plus an approval reference. Switching it on needs `wiw_toggle_nominations` (Administrator), a "CEO approval reference" text, and an audit row. With the flag off, the Nominate page shows "Nominations open soon" and the email sign-up (REQ-106, 195; consent recorded, one-click unsubscribe). There is no flag for judging or announcements.
- The nominator's email is verified by a **single-use signed link** (F6). Duplicates are detected on the normalised nominee name, organisation and category; submissions are rate limited per IP and per email.
- Nominee data is private (`wiw_nominations`, admin-only). After screening, the system emails the nominee a signed link to accept, decline or correct. A decline removes the nominee and adds them to the suppression list (REQ-198, 207).
- **Judging is offline.** Judges are names on a panel (`wiw_panel_members`), not users. Panel conflicts and scores (`wiw_conflicts`, `wiw_scores`) are entered by staff with `wiw_manage_awards` (Editor, Administrator) using the weights in REQ-200. A score cannot be saved for a judge in conflict with that nomination (REQ-036). The totals are computed. Every entry is audited with who entered it and when.
- Awards screens never show commercial data (REQ-037, 199): they never query `Commerce` tables (architecture test).
- Eligibility screening, shortlist and final approval are separate recorded steps with named approvers (REQ-202). **Shortlist, winner and announcement pages publish through the normal publish step**, with no flag (D-037). Public nominee pages are built from a reviewed profile at shortlist only (REQ-198).

### 3.7 Flow F6: public requests and signed links, with no accounts (REQ-229, 232, 233, 157, 158, 018, 090)

**Forms** (each protected by Turnstile, a per-IP and per-email throttle, and a time-based form token instead of a WordPress nonce, because public HTML is cached at the edge and a nonce would be stale): enquiry (to a profile; relayed, the owner's email is never exposed), correction, removal, report, lead ("Get featured"), contact, nomination, "Nominations open soon" sign-up, and data request.

**Support inbox.** Enquiries, corrections, removals, reports and complaints all create a `wiw_support_items` row with a type, a status, an owner, an age and, for removals, a due date shown as a countdown against the REQ-158 targets. There is no separate corrections screen. Editors and Administrators work the inbox (`wiw_support_inbox`); a Reviewer does not see it (D-044). Staff act by editing or unpublishing the profile directly; every edit and unpublish is audited (D-040). A dispute is handled by whoever holds `wiw_handle_disputes` (the Editor by default; REQ-174, REQ-244); an unresolved one goes to the Administrator.

**Requester verification.** A correction, removal or report requires the requester's email to be verified by a signed link before it counts as valid. Staff judge whether a removal is valid and check the requester's identity by hand (default: an official work email or the organisation's domain is accepted; anything else goes to staff judgement).

**Signed links** (`wiw_signed_links`) carry every account-free flow: nominator verification, nominee accept, decline or correct, customer intake, consent for complimentary profiles, and requester email verification (REQ-233).
- The token is 32 random bytes, stored only as a hash. It is bound to one purpose and one record, expires (default 14 days; shorter for nominator and requester verification; Q58), works once, and carries no personal data in the URL.
- A `GET` shows the page; **consuming the link needs a `POST`**, so email scanners that open links do not burn it. After use the link shows the "expired or already used" page.
- Staff can revoke or reissue a link; every use is audited. Customer intake saves a draft against the link until submitted or expired; the draft is private and appears in the review queue as "submitted" on submission (REQ-013).

---

## 4. Data model

IDs: WordPress post IDs plus a public `record_id` or ULID where the contract needs one. Money = integer minor units plus currency. All custom tables use the `wiw_` prefix after the WordPress table prefix. **[PII]** = personal data; **[SENS]** = high-sensitivity (two-factor secrets, evidence uploads); **[FIN]** = commercial/financial; **[PUB]** = may appear publicly after approval.

### 4.1 WordPress content types and taxonomies

| Item | Kind | Notes |
|---|---|---|
| `wiw_profile` | custom post type | Individual, organisation, institution (entity type stored in `wiw_profile_state`). No archive, no feed, no REST, `exclude_from_search`; URLs are served by our own rewrite and handler (section 5). The post stores the name (title) and slug; **fields are post meta** registered from the allowlist per entity type (REQ-003 to 005, 139, 140); no field exists for forbidden attributes. |
| `wiw_story` | custom post type | Articles: interview, feature, spotlight, opinion, announcement, news (REQ-027 to 029). Uses the block editor, sanitised on output; scheduled publishing; Sponsored link. |
| `wiw_sponsor` | custom post type | Name, logo, link; attached to editions, categories and pages (REQ-186). |
| Pages | core `page` | About, How we compile (REQ-130), Terms, Privacy, Cookies, Awards rules (REQ-120), Contact. |
| `wiw_industry`, `wiw_sector`, `wiw_profession`, `wiw_org_type` | custom taxonomies | Controlled lists (REQ-009, D-039). Editors and Administrators add and edit terms in WordPress's native term screens; Reviewers can only assign existing terms. |
| `wiw_place` | hierarchical custom taxonomy | Countries at the top (Nigeria first, other African countries and pan-African bodies), cities beneath, with ISO code as term meta (D-039). Shape to confirm (Q78). |

### 4.2 Custom tables

| Table | Key columns | Notes |
|---|---|---|
| `wiw_profile_state` | post_id PK, record_id (unique, nullable), entity_type, tier, origin (import, customer, complimentary, nominee), **pub_state**, trust (none, verified, featured), sponsored_by / sponsored_until, source_id, batch_id, content_hash, imported_by, reviewed_by, reviewed_at, stale_at | One row per profile. Indexed on pub_state, source_id, batch_id and type for the filters and groupings of REQ-242 and bulk unpublish. |
| `wiw_profile_versions` | id, post_id, version_no, snapshot JSON **[PII, PUB after approval]**, author_user_id (null for import), created_via (import, staff, intake), reason, created_at | Append-only version history (REQ-125, 164). The audit log carries the old and new values too. |
| `wiw_field_provenance` | post_id, field_key, source_id, source_url, retrieved_at, confidence, method, source_hash, passage_id | REQ-142, 143. Queryable for the Sources screen and reports (T-3). |
| `wiw_claims`, `wiw_passages`, `wiw_verification` | claim text and quote, passage text, verification report JSON | Admin-only, never public (REQ-166 to 168, 214). Deleted or reduced after review per retention. |
| `wiw_profile_search` | post_id, name, org, industry, profession, place, text (FULLTEXT), reviewed flag | Rebuilt from public states only (ADR-010). |
| (none) | | Old-slug redirects are core WordPress behaviour (REQ-129 withdrawn, D-053); no redirects table. |
| `wiw_import_batches`, `wiw_import_results` | batch id, contract version, file hash, setting snapshot, status, cursor; record_id and result code | REQ-237. The result export carries no field values. |
| `wiw_sources` | source_id, name, snapshot version, last_seen | A read-only snapshot used by the Sources screen (Q74). Counts (profiles, last import, errors, removals) are computed from `wiw_profile_state`, `wiw_import_results` and the removal log (REQ-243). There is no create, edit, approve or reject path. |
| `wiw_duplicates` | post_a, post_b, score, status | Human merge review (REQ-123, 146). |
| `wiw_suppression` | id, entity_type, name_normalised **[PII]**, identifiers, reason, added_by, added_at | Permanent (REQ-147, 207). Never purged by retention. Exported as a snapshot to acquisition (contract section 11). |
| `wiw_bulk_actions` | id, filter JSON, reason, count, actor, created_at, restored_at | REQ-193. |
| `wiw_support_items` | id, type (enquiry, correction, removal, report, complaint, data_request), post_id, requester name/email **[PII]**, message **[PII]**, status, owner_user_id, due_at, ip_hash | REQ-089, 158, 232. Retention job. |
| `wiw_leads`, `wiw_customers` | name, org, interest, contact **[PII]**, owner_user_id, status, ext_id | REQ-177, 178, 205. |
| `wiw_arrangements`, `wiw_arrangement_links` | customer, type, amount_minor **[FIN]**, currency, method, reference **[FIN]**, received_on, confirmed_by, status (computed cache), starts_on, ends_on, complimentary, stream, notes, ext_id | REQ-179. Never joined to awards tables. |
| `wiw_editions`, `wiw_award_categories`, `wiw_criteria`, `wiw_nominations`, `wiw_nomination_stages`, `wiw_panel_members`, `wiw_conflicts`, `wiw_scores` | nominator **[PII]**, nominee link, stage, evidence **[PII]**, judge name, criterion, score, entered_by, entered_at | REQ-030 to 038, 196 to 202, 234. Private until shortlist. |
| `wiw_signed_links` | token_hash, purpose, subject_type/id, email **[PII]**, expires_at, used_at, revoked_at | REQ-233. |
| `wiw_notes` | notable (type, id), body **[PII maybe]**, author | REQ-187. Never public. |
| `wiw_consents` | subject_email **[PII]**, purpose, policy_version, granted_at, revoked_at | REQ-058, 061, 195. |
| `wiw_email_events` | message_id, type (sent, bounce, complaint), at | REQ-075. |
| `wiw_view_counts_daily` | post_id, date, count | REQ-019 is withdrawn; counts are kept for the staff report only (Q23 default; bots filtered, no IPs stored). |
| `wiw_audit_log` | id, at, actor_id, action, subject_type/id, old JSON, new JSON, prev_hash, hash | Append-only, hash-chained (ADR-013; REQ-051, 188, 228). |
| `wiw_processors` | name, purpose, location, dpa_ref | REQ-065. |
| WordPress options (autoload off) | settings: thresholds, stream defaults, SLA targets, wording of the pending label, import-all-as-not-public, the Nominations flag and its approval reference | Brand values are **not** here; they live in the theme tokens file. |

PII handling: the PII-catalogue file (`whoiswho-core/config/pii.php`) lists every personal-data column and post-meta key. It drives the retention job (REQ-063), the export log (REQ-069), log scrubbing and the data-request export. A test fails if a column marked as personal data is missing from it.

### 4.3 Roles and capabilities (D-044, D-046, REQ-235, 244)

Three staff roles are **default bundles of named capabilities** (`wiw_reviewer`, `wiw_editor`, and the WordPress `administrator` role plus every `wiw_*` capability). WordPress's built-in subscriber, contributor, author and editor roles are removed on activation; `default_role` is empty and registration is closed. The **Administrator can grant or revoke individual capabilities for a user, across roles**, through a *Users and capabilities* screen. WordPress stores a per-user grant or an explicit `false` in the user's capabilities, so no extra mechanism is needed; the screen audits every change. Menus, routes and actions follow the user's *effective* capabilities.

The capability list and the default role mapping are kept in one place only: `docs/requirements.md`, section "Capabilities and default role mapping" (D-053). It lists which capabilities are core WordPress and which `whoiswho-core` adds (`wiw_*`). Differences from the earlier list here: profile and story editing, unpublishing a story, term management, user management and plugin installation now use core WordPress capabilities (the profile type's generated `edit_wiw_profiles` family, `edit_posts` family, `manage_terms`, `create_users`/`promote_users`, `install_plugins`) instead of `wiw_edit_profiles`, `wiw_manage_stories`, `wiw_manage_taxonomies` and `wiw_manage_users`; `wiw_view_profiles` becomes core `read_private_wiw_profiles`; `wiw_receive_enquiries` is new (Q62).

Capabilities that cannot be granted to a non-Administrator (proposed: users and capabilities, settings, the register, plugin install; Q72) are enforced by a `user_has_cap` filter, not only by the screen. The Reviewer's bulk-unpublish capability can unpublish one profile through the filter, so a Reviewer can act in an emergency without edit rights. The Reviewer menu is exactly: Dashboard (review tiles), Review queue, Duplicates, Stale records, Profiles, Sources (read-only), Suppression list (read-only); it grows only by grants (REQ-240, 244).

---

## 5. Module and route outline, and auth

**There is no public JSON API.** Core REST is locked to logged-in staff by `whoiswho-guard`; there is no public REST route. Bounce handling is manual until a mail provider with webhooks is added. Pages are server-rendered; forms post to the same site.

| Area | Routes (outline) | Auth |
|---|---|---|
| Public pages | `/`, `/about`, `/people`, `/businesses`, `/institutions`, `/industries`, `/industries/{term}`, `/stories`, `/stories/{slug}`, `/awards`, `/awards/{category}`, `/awards/winners`, `/profile/{slug}`, `/search`, `/sitemap.xml`, `/robots.txt`, legal pages, `/how-we-compile` | none; HTML cached at the edge for at most 120 s |
| Public forms | `POST /enquire/{slug}`, `/correct/{slug}`, `/remove/{slug}`, `/report/{slug}`, `/subscribe` (lead), `/contact`, `/nominate` (flag), `/newsletter`, `/data-rights` | none; Turnstile, throttle per IP and email, form token, validation |
| Signed links | `GET /link/{token}` (page), `POST /link/{token}` (consume) for nominator, nominee, intake, consent and requester verification | token; single use; expiry; rate limited |
| Staff sign-in | `/wp-login.php` (Cloudflare rate-limited; no link on the site), then two-factor | password plus two-factor, enforced before first use of wp-admin |
| wp-admin screens | Dashboard (by role) · Review queue · Duplicates · Stale records · Profiles (filter and group by source, batch, type, state; bulk unpublish) · Sources (read-only) · Suppression list · Support inbox · Stories · Taxonomy term screens · Nominations, panel and conflicts, score entry, results · Leads · Commercial register · Imports · Users and capabilities · Roles and permissions (matrix and menus) · Audit log · Settings · Purge log | each screen and action checks a named capability; deny by default |
| Health | `GET /healthz` (database, job heartbeat, disk) | none; no detail; token for verbose output |
| Scheduled jobs | purge, email, import chunks, sponsored apply and expire, expiry reminders, stale checks, retention, backup, view-count roll-up, SLA flags | WP-Cron or CLI cron |

Authorisation rules: deny by default; every admin action checks a capability and, where it applies to a record, a policy-style check in the module; destructive actions need a reason and write an audit row; tests assert 403 for each role and capability pairing, plus a pairing with a granted and a revoked capability.

---

## 6. Security

### 6.1 Threat model (top threats and controls)

| Threat | Control |
|---|---|
| A profile that should not be public appears on a surface WordPress or a plugin adds (feed, REST, sitemap, oEmbed, search, preview, attachment) | F1 state machine; `whoiswho-guard` closes core surfaces; no SEO plugin; the matrix test of 3.2; spike S5 |
| Removed content stays visible | F2: 410 at the origin from database state; purge; two-minute worst case at the edge; restore by batch |
| Wrong or defamatory facts about a real person | Per-field provenance, source beside each claim in review, neutral checklist (REQ-163), corrections log (REQ-164), removal service level (REQ-158), bulk unpublish, permanent suppression |
| Poisoned or tampered import | Contract checks at import: verification report, content hash recomputed (REQ-221), suppression re-checked, allowlisted fields only, forbidden fields refused, undo a batch, audit |
| Staff account takeover | Enforced two-factor with recovery codes; strong passwords and breach-list check; lockout; Cloudflare rate limit on the login; session revocation; idle timeout; optional IP or country rule for wp-admin |
| Insider misuse and data export | Least-privilege capabilities; exports Administrator-only and logged (REQ-069); audit log; every grant of a capability audited |
| Abuse of bulk unpublish | Capability limited to Administrator and Reviewer by default; mandatory reason; count confirmation; audit; restore by batch |
| Pay-to-win leakage | Architecture tests: Awards does not reference Commerce; Commerce and Sponsored never write Verified or Featured |
| Spam, rival-driven removal requests and enumeration | Turnstile, throttles, duplicate detection, email verification by signed link, staff validity check on removals (hide within two business days, not instantly), uniform responses |
| Signed-link theft or scanning | Single use consumed on `POST`, expiry, hashed storage, rate limit, revocation, audit |
| XSS and injection | Escape on output (`esc_*`), `wp_kses` allowlists for story bodies, prepared statements only, nonces on admin actions, a Content-Security-Policy, lint rules against raw output and raw SQL |
| Malicious uploads | Image types allowlisted for public uploads and re-encoded server-side (strips EXIF and payloads); evidence uploads limited to JPG, PNG, PDF, size-limited, stored outside the web root, scanned if a scanner is available (spike S4), never served inline |
| Plugin and theme supply chain | Few plugins, all pinned by a lockfile and updated through the pipeline; `DISALLOW_FILE_MODS` in production; an allowlist check in CI; weekly vulnerability scan |
| Secrets exposure | See 6.3 |
| Cache deception or poisoning | Cache only anonymous GET HTML for named public paths; bypass on staff cookies and wp-admin; vary rules tested |

### 6.2 Authentication and authorisation
- **Staff only.** Password plus two-factor, enforced before first use of wp-admin (REQ-047); recovery codes; lockout after repeated failures; sessions revocable by the user and by the Administrator; idle timeout (60 minutes in wp-admin, verify). The two-factor method is a maintained plugin or custom code (spike S6, Q76).
- **Closed registration.** `users_can_register` off, no default role, no front-end login link, no application passwords, XML-RPC off, user enumeration blocked (`?author=`, the users REST route, user sitemaps, login error messages).
- **No other accounts.** Visitors, profile owners, nominators, nominees and judges have none (REQ-229). Their flows use forms and signed links (3.7).
- **Capabilities** are checked on every admin action and on menus (REQ-240). Sensitive actions (imports, exports, register, users and capabilities, suppression changes, the Nominations flag) are limited to the Administrator by default.
- **wp-admin hardening** (in `whoiswho-guard` and server config): file editing and file modifications disabled in production; automatic plugin updates off (updates ship through the pipeline); wp-config outside the web root; directory listing off; PHP execution blocked in uploads; core, plugin and theme paths read-only to the web user where cPanel allows.

### 6.3 Secrets
- Real values exist only in: a `.env`-style file **outside the web root** (mode 600, created by you in cPanel or over SSH; N18) and GitHub **Environment** secrets if automated deploys are added later. cPanel has no secret manager, so that file is the host secret store (D-020). `wp-config.php` is committed as a template with no secrets and loads that file.
- `.env.example` lists names only. Agents never read `.env*` (CLAUDE.md). A secret scanner runs in CI and as a pre-commit hook.
- Keys: SMTP credentials, and later, if used, a Cloudflare API token (cache purge on one zone), Turnstile, an optional Sentry DSN, the signed-link and form-token signing keys, the backup passphrase, and the WordPress salts.
- Rotation is documented in the runbook.

### 6.4 PII and payment handling
- No card or bank account data. The register holds method and reference text; a digit-pattern guard blocks card-like numbers in the reference field.
- Personal-data columns and meta keys are catalogued (`pii.php`), kept out of logs and error reports, encrypted at rest where sensitive (evidence files), and covered by retention, the data-request export and erasure (REQ-059, 063).
- Individual stubs follow the REQ-139 and 140 allowlist: the schema has no field for forbidden attributes, and free text is pattern-scanned on the pipeline side and again at import (REQ-141).
- Processor register and data-processing agreements: hosting, cPanel mail, and any of Cloudflare, error reporting or an uptime monitor if added later (REQ-065). The AI provider belongs to the acquisition side's register.
- Breach procedure in the runbook (REQ-066).

### 6.5 Validation, rate limits, dependencies, logging, backups
- **Validation:** server-side only; length limits; Unicode-safe names (REQ-053); one validator class per form.
- **Rate limits:** per IP and per email or link token for each public form (REQ-122); plugin throttles on the login, forms and signed links, plus Cloudflare rate-limiting rules if Cloudflare is added.
- **Dependencies:** Dependabot weekly; `composer audit` and `npm audit` fail CI on high or critical; lockfiles committed; a WordPress vulnerability database check for plugins (option, **verify**); minimal packages; the reviewer agent reads dependency PRs.
- **Headers:** HSTS, a nonce-based CSP, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, frame-ancestors none.
- **Logging:** structured, IDs not names or emails, request ID, 30-day retention; the audit log is separate and kept.
- **Backups (REQ-049):** nightly database dump and private-file archive from cPanel backups, plus a backup plugin or export if needed; **restore test** into a local copy before launch and monthly (RPO 24 h, RTO 8 h).
- **Audit-log immutability on shared hosting:** append-only writer with no update or delete methods, hash chain, database triggers that block UPDATE and DELETE if the account has TRIGGER privilege (checked on the live host at import time), and a periodic copy of the log off the server (a download by the Administrator is enough) so tampering is detectable (ADR-013).

### 6.6 Legal exposure (not legal advice)

*Deferred (Legal skipped for now, D-051): nothing in the build waits on Legal. Legal-dependent items (individuals going public, photos, retention periods, data transfers) are decided later and are guarded by the review gate and bulk unpublish.*
Publishing personal data without consent (D-013) is the largest exposure (NDPA 2023, defamation, false light). The architecture cannot remove it but limits it: individuals and entrepreneurs are never public before human review, organisations carry a visible label and noindex until reviewed, every field has a source, removal has a two-day service level, suppression is permanent, and bulk unpublish plus 410 and purge can take down a whole batch quickly. Legal's written position is outstanding (Q5, Q35).

---

## 7. Testing strategy and CI gates

Tests are written first (CLAUDE.md). Test names cite REQs, for example `test_REQ_208_hides_unreviewed_profiles_from_sitemap`.

| REQ type | Test approach |
|---|---|
| Visibility and publication (208, 209, 210, 216 to 218, 193, 147, 207) | Integration-test matrix over every state and every surface (3.2); F2 test with a faked Cloudflare client asserting purge calls and the 410 path; a live-host latency check; state-transition tests for illegal moves |
| Authorisation (083, 109, 235, 240, 244) | Role and capability pairing matrix of 200 and 403 responses; granted and revoked capability cases; menu tests; architecture tests for module boundaries |
| Import (092, 132 to 148, 161, 212, 214, 221, 237) | Contract fixtures and the worked example's hash as a test vector; dry-run diff; field allowlist; forbidden fields refused; provenance required; suppression re-check; idempotent re-import; undo; a 1,000-record chunked run |
| Business rules (022, 023, 179 to 184, 112) | Unit and property tests for the counting rule, money and label separation |
| Forms and links (018, 081, 122, 196, 232, 233) | Validation, throttling, fake Turnstile, signed-link single use, scanner-style `GET` that must not consume, expiry |
| Awards (030 to 038, 196 to 202, 234) | Conflict blocks score entry; computed totals; flag on and off; no commercial data reachable |
| Privacy (057 to 072) | Data-request workflow, retention job, PII catalogue completeness |
| UI, accessibility, performance (040 to 043, 055) | Playwright end-to-end on Chromium and a WebKit mobile profile; axe on key pages; Lighthouse CI budget (LCP, CLS) on the local build |
| Security (046) | Static analysis, `composer audit`, `npm audit`, secret scan, weekly baseline scan of the local build, header test, no registration or login route exposed to visitors (REQ-229) |
| Theme (052, 190) | CI check that fails if brand colours or fonts appear outside the tokens file |

**CI gates on every PR (all required):** PHP coding standards (PHPCS with the WordPress ruleset) → static analysis (PHPStan with a WordPress extension, level agreed at M0; **verify**) → PHPUnit unit and integration tests on the WordPress test suite, plus architecture tests, with a coverage threshold for `src/` → asset build → Playwright smoke and axe → dependency audit → secret scan → the PR must cite REQ IDs and `Closes #N`. A separate reviewer step marks work done.
**Gates:** every PR passes the CI gates before merge. Going live is a manual migration (section 8) approved by you; automated staging and production deploys can be added later.

---

## 8. Deployment

| Item | Design |
|---|---|
| Environments | **Local** (wp-env or an equivalent local WordPress; the main build and test place), then **live** (wiwi.africa on cPanel). A staging site is optional; D-029 applies to whichever site is public: real data only after the import safeguards pass (Q64). |
| Accounts (N18) | Needed now: **GitHub**, and **cPanel** (PHP version selector, a database and user, a mail account for SMTP). **Later, optional:** Cloudflare, Brevo, uptime monitor, Sentry. Object storage is not planned. Optional: Google Search Console (N21). There is no AI provider account on the site side. |
| Build | For go-live, build an artifact: WordPress core (pinned), plugins and the theme (from the lockfile; third-party plugins pinned from the plugin directory, **verify the mechanism**), production Composer autoloader, built assets, a version stamp. |
| Release (go-live migration) | Move the finished local site to the live host: copy the code (SSH, rsync or the cPanel file manager), import the database and uploads, set `wp-config.php` and the secrets file, run the plugin's forward-only migrations, then run smoke checks. Releases into `releases/<timestamp>/` with a `current` symlink are an optional later improvement. |
| Config and secrets | Section 6.3. |
| Migrations | Forward-only and **expand/contract** (additive first, remove later), so the previous release still runs against the new schema. A pre-migration database dump is taken automatically in production. |
| Rollback | Switch `current` to the previous release (seconds). A database rollback means restoring the pre-deploy dump. |
| Cron | WP-Cron with Action Scheduler. If the live host allows it, a cPanel cron every minute can drive it instead. |
| Monitoring | Failed-job alerts and a backup check in wp-admin. An uptime monitor is later, optional (REQ-045). |
| Runbook | `docs/runbook.md` (REQ-095): deploy, rollback, restore, incident, bulk unpublish, key rotation, move to a VPS. |
| **Local build and go-live checklist** | Build and test locally (all CI gates). Then, on the live host at import time: PHP version and memory and time limits, a database and user, SMTP sends and SPF/DKIM/DMARC, the uploads folder is writable, cron or WP-Cron runs jobs, a 1,000-record import in chunks, 410 behaviour, TRIGGER privilege for the audit-log triggers, and a restore test. Each result is written to the runbook. Failures lead to a fix or the VPS fallback; none of these block building. |

**Fallback hosting (if the live-host check fails):** one small VPS (2 vCPU, 4 GB) in an EU or Africa region with Nginx or Caddy, PHP-FPM, MariaDB and a supervised worker, using the same release script. Same code, same CI.

---

## 9. Cost (monthly, estimates to verify at purchase)

| Item | Now | At 10x (≈1M views a month, 10 to 20k profiles, 2 to 3 staff) |
|---|---|---|
| cPanel hosting (already bought) | US$0 extra | replace with a VPS or managed WordPress hosting: **verify**, about US$20 to 60 |
| Domain wiwi.africa | already paid (annual) | same |
| Cloudflare (optional, later) | Free | Free, or a paid plan for better WAF and analytics (**verify**) |
| Email | cPanel mail, included | a mail provider such as Brevo, about US$9 to 25 (**verify**) |
| Error reporting and uptime monitoring (optional, later) | Free (**verify**) | about US$26 plus up to US$10 (**verify**) |
| Paid WordPress plugins | none planned (custom plugin instead) | a 2FA or scanning plugin if chosen (**verify**) |
| **Total** | **about US$0 to 5** | **about US$80 to 200** |

No AI cost sits on the site side; the pipeline's cost is in the acquisition plan. The biggest cost driver is people (reviewers), not hosting.

---

**CaromHQ sync is one of the last things built** (Q16). Nothing earlier depends on it; the CSV export is the only early path.

## 10. Parallelism plan

### 10.1 Areas and ownership

Website code lives in one folder, `site/` (proposed; see the report on how CLAUDE.md names it). Rule: an agent edits only its area's paths; cross-area needs become an issue for the owning area. Migrations are written only by `plugin` (versioned files, one table group per PR). Each module has its own route file.

| Area | Owns | Notes |
|---|---|---|
| **plugin** | `site/wp-content/plugins/whoiswho-core/**`, `site/wp-content/mu-plugins/**`, `site/bin/**`, `tests/Unit`, `tests/Integration`, `tests/Arch` | Modules, state machine, capabilities, admin screens, jobs, importer, mail classes |
| **theme** | `site/wp-content/themes/whoiswho/**`, the tokens file, assets, `tests/e2e` for UI specs | Templates, components, tokens, accessibility, SEO output templates (markup is produced by the plugin's read models) |
| **infra** | `.github/`, `deploy/`, `scripts/`, `site/composer.json` and lockfile, `site/wp-config.php` template, `.env.example`, `docs/runbook.md` | CI, go-live migration, backups, runbook; Cloudflare and mail-provider setup docs only if those are added |
| **qa** | `tests/e2e/`, `tests/Visibility/`, `tests/Security/`, `docs/traceability.md`, `docs/verify-*.md` | Independent verifier; writes no feature code |

Interface contracts: the plugin exposes view-models through the read-model classes, documented per page in `docs/contracts/`; the theme builds against fixtures; infra owns the deploy command and the CI job names; the import contract is the only interface with the acquisition side.

### 10.2 Foundation (serial, M0; in this order)

1. **Scaffold** (infra, plugin): `site/` project with a pinned WordPress, the three code packages, local environment, PHPCS, static analysis, PHPUnit, Playwright, asset build.
2. **CI** (infra): all gates of section 7 with empty suites passing, Dependabot, secret scan.
3. **Go-live migration script** (infra): the steps in section 8 as a repeatable script; run it on a live host only when the local site is ready. Not needed to start building.
4. **Platform core** (plugin): roles and capabilities with grants, enforced two-factor, closed registration, `AuditLogger` and the hash chain, settings, the Nominations flag, the PII catalogue.
5. **Core data and the choke point** (plugin): `wiw_profile`, `wiw_profile_state`, versions, provenance, taxonomies, suppression, **`StateMachine`, the `wp_insert_post_data` guard, the `whoiswho-guard` mu-plugin and the `PublicProfile` read model, with the REQ-208 matrix test**.
6. **Theme shell** (theme): tokens, base layout, components, theme-leak CI check.
7. **Architecture tests** (plugin, qa): module boundaries, single writer of `pub_state`, single writer of the audit log.
8. **Import path, T-0** (plugin): contract validator, hash check, dry run, chunked apply, undo, result export; the review queue and dashboard counts so reviewers can start (REQ-237 is the first acquisition task).
9. **Cache and 410** (plugin): purge service with a fake, the 410 handler, bulk unpublish.

After M0 these run in parallel waves: directory pages and search; review queue details; Support inbox and public forms; signed links; Commerce register and dashboard; stories; awards and score entry; users and capabilities screen; email templates and consent; analytics; runbook and backups.

---

## 11. Top risks and checks

| # | Risk | Check or mitigation |
|---|---|---|
| 1 | **The cPanel account may not meet the needs** (PHP version, memory and time limits, cron, MariaDB version, TRIGGER privilege, outbound HTTPS, inodes, uptime). | Checked on the live host at import time (section 8). Failure leads to a fix or the VPS fallback. Does not block building. |
| 2 | **Immediate unpublish may not be immediate** (indexed pages; edge caches if Cloudflare is added). | The origin returns 410 at once. If Cloudflare is added, test purge by URL and a bulk action of several hundred profiles, and record the time to 410. |
| 3 | **Import throughput on shared hosting** (1,000 records with provenance rows, memory and time limits). | Test a 1,000-record fixture in chunks locally and again on the live host; tune the chunk size; prove resume after a forced time-out. |
| 4 | **Upload scanning** (a virus scanner may be unavailable). | Restrict types, re-encode images, flatten PDFs and accept evidence files only for staff review; use a scanner if the host offers one. |
| 5 | **A WordPress surface leaks a non-public profile** (core sitemap, feed, REST, oEmbed, search, attachment, preview, SEO output, a future plugin). | Build the matrix test first against a bare install, then against every plugin added; keep the plugin list short and re-run the matrix for each addition. |
| 6 | **Two-factor** (WordPress has none built in). | Use one maintained plugin enforced for every staff role, with recovery codes; test enforcement and lockout locally (Q76). |
| 7 | **Email volume and deliverability on cPanel mail** (SPF, DKIM, DMARC; hourly sending caps; bulk nominee mail). | Check at go-live; batch the sends; move to a mail provider only if the caps bite. |

Other watch items: shared-host uptime against the 99.5% target (REQ-045); review capacity (measured by the pilot; REQ-206); Legal's position (Q5, Q35; Legal is skipped for now, D-051).

---

## 12. ADRs

One short record per decision in `docs/adr/`. Status of each is listed here.

| ADR | Decision | Status |
|---|---|---|
| ADR-001 | WordPress with a custom plugin, a guard mu-plugin and a theme; server-rendered | Proposed v2 |
| ADR-002 | MariaDB/MySQL through `$wpdb`; custom tables for product data | Proposed v2 |
| ADR-003 | cPanel behind Cloudflare; VPS fallback | Proposed v2 (kept) |
| ADR-004 | wp-admin with three roles and capability grants; no judge area; staff-only auth | Proposed v2 |
| ADR-005 | Publication model: one state machine, status mapping, versions table | Proposed v2 |
| ADR-006 | Edge caching, 410 from database state, purge, bulk unpublish | Proposed v2 |
| ADR-007 | Media and private files | Proposed v2 |
| ADR-008 | Email: cPanel SMTP through `wp_mail` | Proposed v2 |
| ADR-009 | AI drafting | **Superseded**: it is in the acquisition pipeline |
| ADR-010 | Search: FULLTEXT on a public-only table | Proposed v2 |
| ADR-011 | Jobs: system cron driving a queue library; WP-Cron off | Proposed v2 |
| ADR-012 | CI/CD to cPanel by GitHub Actions | Proposed v2 |
| ADR-013 | Audit log: append-only, hash-chained, copied off-host | Proposed v2 |

---

## 13. Decision record: WordPress chosen (D-031)

On 2026-11-06 the first draft proposed Laravel + Filament and compared it with five other platforms. You then decided on **WordPress on cPanel** (D-031). This section keeps the comparison as history and records what the decision costs, so the design above can answer it. The facts were read from vendor pages on 2026-11-06; **n.v.** means not verified.

**Options compared:** A Laravel + Filament (the earlier proposal), B WordPress, C Strapi, D Directus, E Payload, F Statamic.

**Scorecard (1 = poor, 5 = excellent; my judgement, written when I proposed A, so it is biased toward A).** Totals out of 55: A 49, **B 35**, C 30, D 32, E 37, F 39. B scored highest for editor ease (5) and hosting fit on cPanel (5), and lowest on the gating rules: nothing public before approval (3), immediate unpublish (3), per-field provenance (2) and security surface (2). Judge isolation (2) no longer applies, because there are no judge accounts (D-034).

**Licensing facts that still matter for WordPress:** WordPress is GPLv2 or later. A custom editorial status flow needs no paid plugin here, because the state machine is custom code. The paid status plugin that the first comparison priced (US$49 to 199 a year) is therefore not needed. Whether any third-party plugin is free for our use is **verify** per plugin.

**What the choice leaves open, and the design response:**

| Weakness found in the comparison | Response in this design |
|---|---|
| Plugin surface can leak drafts (previews, caches, SEO plugins) | `whoiswho-guard` closes core surfaces; no SEO plugin; matrix test on every surface |
| One user and capability model, plugins can read everything | Awards never touch commercial data by test; no judge accounts exist; few plugins; capability checks on every action |
| No per-field provenance | `wiw_field_provenance` table, queried by the review queue and the Sources screen |
| No immutable audit log | `wiw_audit_log` with a hash chain and an off-host copy |
| Largest attack surface and most attacked CMS | No front-end accounts, enforced two-factor, hardening, file modifications off, pinned and scanned dependencies |
| Sitemap, search and feed defaults were not verified | Replaced by our own sitemap, search and feed handling (3.2, 5) |

**Other platforms** (history only): Strapi and Payload gate review workflow or audit logs behind enterprise tiers; Directus changed its licence and lacks an approval workflow; Payload and Directus do not run on a typical cPanel; Statamic needs a paid licence for roles and revisions and defaults to flat files. The first draft's spike S6 (compare the thinnest REQ-208 and REQ-209 slice in A and E) was dropped when you chose WordPress.

### Sources (read 2026-11-06)

- Strapi: https://strapi.io/pricing-self-hosted · https://docs.strapi.io/cms/features/review-workflows · https://docs.strapi.io/cms/features/audit-logs · https://docs.strapi.io/cms/features/draft-and-publish · https://docs.strapi.io/cms/deployment · https://docs.strapi.io/cms/api/rest/status · https://github.com/strapi/strapi/blob/develop/LICENSE
- Directus: https://directus.com/pricing · https://directus.com/mscl · https://github.com/directus/directus · https://directus.com/docs/guides/auth/access-control · https://directus.com/docs/guides/security/best-practices · https://directus.com/docs/guides/content/content-versioning · https://directus.com/docs/self-hosting/overview
- Payload: https://payloadcms.com/docs/getting-started/installation · https://payloadcms.com/docs/versions/drafts · https://payloadcms.com/docs/versions/overview · https://payloadcms.com/docs/access-control/overview · https://payloadcms.com/docs/production/deployment · https://payloadcms.com/docs/database/overview · https://payloadcms.com/enterprise/publishing-workflows · https://github.com/payloadcms/payload/blob/main/LICENSE.md
- Statamic: https://statamic.com/pricing · https://statamic.com/blog/statamic-price-increase · https://statamic.com/features · https://statamic.dev/licensing.md · https://statamic.com/license · https://statamic.dev/requirements · https://statamic.dev/revisions · https://statamic.dev/tips/storing-users-in-a-database.md
- WordPress and PublishPress: https://wordpress.org/about/requirements/ · https://wordpress.org/about/license/ · https://developer.wordpress.org/reference/functions/register_post_type/ · https://wordpress.org/documentation/article/post-status/ · https://developer.wordpress.org/rest-api/reference/posts/ · https://publishpress.com/statuses/ · https://publishpress.com/pricing/
- Laravel and Filament: https://laravel.com/docs/releases · https://laravel.com/docs/12.x/deployment · https://filamentphp.com/docs/5.x/introduction/installation.md · https://github.com/filamentphp/filament

**Not verified (do not rely on):** Directus Node and database requirements and cPanel fit; Directus self-hosted seat caps under the grant; Strapi custom-role and field-level permission details; Payload field-access details and Cloud pricing; Statamic entries-in-database support and scale; WordPress sitemap, search and feed defaults and security guidance; PublishPress free-versus-paid split. Conclusions about cPanel fit for Strapi, Directus and Payload are my inference from their Node and Docker requirements.

---

## 14. Requirements coverage

Every REQ that is not withdrawn is handled in the section shown. Withdrawn rows (for example 010 to 012, 017, 019, 035, 048, 070, 073, 074, 076, 097, 099, 107, 110, 111, 113 to 114, 117 to 119, 124, 128, 151, 152, 173, 185, 201, 213, 215, 219, 220, 223 to 225, 227, 230, 231) have no design here.

| REQ group | Section |
|---|---|
| Public pages, content types, directory, filters, search (001 to 008, 026 to 029, 032, 033, 039, 120, 121, 130, 144, 171) | 3.2, 4.1, 5, ADR-010 |
| Taxonomies and controlled lists (009) | 4.1, ADR-004 |
| Profile fields and allowlists (003 to 005, 139, 140, 142, 143, 145, 162, 203) | 3.4, 4.1, 4.2 |
| Customer profiles and intake (013 to 016, 085, 123, 126, 175, 177) | 3.2, 3.7, 3.5 |
| Trust labels and Sponsored (024 to 026, 150, 153 to 155, 182 to 184, 216, 226) | 3.2, 3.5, 4.2 |
| Publication states and visibility (014, 091, 208, 210, 216 to 218) | 3.2, ADR-005 |
| Immediate unpublish and bulk unpublish (091, 193, 209) | 3.3, ADR-006 |
| Import and acquisition interface (092, 132 to 138, 141, 146 to 148, 161, 176, 192, 207, 211, 212, 214, 221, 222, 228, 237) | 3.4, the contract, ADR-009 |
| Review queue, duplicates, stale records (084, 149, 160, 163, 164, 206, 222, 241) | 3.2, 3.4, 4.3 |
| Sources and provenance on the site (136, 241 to 243) | 3.4, 4.2 |
| Support inbox, corrections, removals, reports, data requests (018, 059, 071, 089, 090, 156 to 159, 172, 232, 236) | 3.3, 3.7 |
| No public accounts, signed links (229, 233) | 3.7, 6.2 |
| Roles, capabilities, menus, audit (047, 051, 069, 083, 108, 109, 188, 235, 239, 240, 244) | 4.3, 5, 6.2, ADR-004, ADR-013 |
| Commercial register and counting rule (021 to 023, 077, 086 to 088, 093, 112, 115, 116, 178 to 181, 186, 187, 189, 205) | 3.5, 4.2 |
| Awards and nominations (030 to 034, 036 to 038, 064, 106, 194 to 200, 202, 234, 195) | 3.6, 4.2 |
| Privacy, retention, consent, processors (057 to 063, 065 to 068, 072, 100) | 4.2, 6.4, 6.6 |
| Security and abuse (045, 046, 081, 122, 127) | 6 |
| Performance, accessibility, browser, language (040 to 044, 053 to 055, 082) | 2, 3.2, 7, ADR-006 |
| Email and analytics (061, 062, 075, 078, 094) | ADR-008, 6.5 |
| Media (042, 080, 127, 203) | ADR-007 |
| Backups, operations, environments, runbook (049, 050, 056, 095) | 6.5, 8, ADR-012 |
| Brand tokens and domain (052, 190, 204) | 2, 10.1 |
| Dashboard and reports (088, 171, 239) | 3.5, 4.3 |
| Timeline items (096, 098, 101 to 105) | tracked in the plan, not designed here |
| Export (131) | 3.4, 4.3 |
| Future items (020, 238) | not designed |

---

## 15. Open architecture questions

Proposed numbers start at Q73 (Q72 is already used by REQ-244). They are not yet in `docs/questions.md`.

| Q | Question | Proposed default |
|---|---|---|
| Q26, Q27 | What the cPanel account offers; which shared services can be reused | **Answered (D-050):** build locally on WordPress; check the host at import time; no new accounts needed now |
| Q28 | Who owns and pays for the new accounts | **Answered (D-049, D-050):** none needed now; SMTP comes from cPanel mail |
| Q72 | (Used by REQ-244) Which capabilities can never be granted to a non-Administrator | Users and capabilities, settings, the register, plugin install |
| Q73 | A customer-submitted profile awaiting approval has no state name in REQ-210. Reuse *Imported – not public* with `origin = customer`? | Yes |
| Q74 | Where does the site get source names for the read-only Sources screen, since the contract carries only `source_id`? | A basic `sources[]` array for now (a MINOR contract change); revisit once acquisition is done |
| Q75 | Media offload to object storage? | **Answered (D-050):** no; the WordPress media library is used (REQ-080 amended) |
| Q76 | Two-factor: a maintained plugin or custom code? | **Answered (D-050):** a maintained plugin enforced for every staff role |
| Q77 | REQ-237 says "CSV or REST" but the contract is JSON Lines. Reword to "file or REST", and confirm files first with no service account? | Yes |
| Q78 | Shape of the places taxonomy: one hierarchical taxonomy with countries and cities, or separate country and city taxonomies? | One hierarchical taxonomy |
| Q79 | Queue library: Action Scheduler (a library) or a small custom job table? | **Answered (D-050):** WP-Cron with Action Scheduler; no spike |
