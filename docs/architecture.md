# WHOISWHO — Architecture

Status: **draft for Gate 2** (2026-11-06). Inputs: `docs/requirements.md` (REQ-001–209), `docs/decisions.md` (D-001–D-030). No application code exists yet.
Assumed constraints (edit if wrong): solo human supervisor + Claude Code agents; low budget; Nigeria first; **no online payment processing** (D-005); easy managed deployment; wiwi.africa and a cPanel account already exist (D-020); Incspired shared services may be available (unknown, see Q26–Q27).

---

## 1. Summary

A **modular monolith**: one Laravel (PHP 8.3) application serving server-rendered public pages, an admin console (Filament), a small owner area and a judge area, backed by **MySQL/MariaDB**, fronted by **Cloudflare** (DNS, CDN, WAF, bot challenge, cache purge), hosted on the **cPanel account you already have**, with a ready fallback to a small VPS if the account fails the capability spike (S1).

Why this shape:
- The product is content, directory search, review queues, a register and a workflow. That is CRUD plus rules, which a server-rendered framework with a ready admin panel does in the least code and the fewest moving parts.
- The two hardest requirements are **"nothing public before human approval" (REQ-208)** and **"unpublish immediately, even on indexed pages (REQ-209)"**. Both are solved by design choices (a single published-revision pointer and edge-cache purge), not by technology fashion.
- Nothing here needs a Node server, a message broker, Redis, Kubernetes or a payment gateway. Everything runs on shared hosting, and each piece can be swapped by configuration if traffic grows (section 9).

| Layer | Choice |
|---|---|
| Backend + frontend | Laravel (current stable) on PHP 8.3, Blade + Alpine.js + Tailwind (public); Livewire only where a form needs it |
| Admin / judge console | Filament (current stable), two panels: `admin`, `judge` |
| Database | MySQL 8 / MariaDB 10.6+ (whatever cPanel offers), InnoDB, utf8mb4 |
| Auth | Laravel session auth; email verification; TOTP MFA mandatory for staff; roles + policies |
| Hosting | cPanel (existing) behind Cloudflare; fallback: 1 small VPS |
| Payments | **None** (offline; admin register) |
| Email | Brevo SMTP (free tier) via Laravel mailer; SES as a drop-in alternative |
| Storage | Cloudflare R2 (public images, backups); private disk for claim documents |
| Search | MySQL FULLTEXT now; Laravel Scout + Meilisearch later if needed |
| AI drafting | Anthropic API (small model), behind a flag and an interface |
| Observability | Sentry (errors), Better Stack/UptimeRobot (uptime), Cloudflare Web Analytics (cookieless) |
| CI/CD | GitHub Actions; deploy by SSH + release directories (or cPanel Git deploy fallback) |

---

## 2. Stack per layer

| Layer | Choice | Why it fits these requirements | Rejected alternatives | Lock-in / scaling risk |
|---|---|---|---|---|
| **App framework** | Laravel + PHP 8.3 (ADR-001) | Runs natively on cPanel (the account already paid for). Has everything the REQs need built in: auth, policies, queues, scheduler, validation, rate limiting, mail, i18n of money/time. Claude Code writes Laravel reliably. SSR gives indexable pages (REQ-044) and fast LCP (REQ-041) with no JS framework. | Next.js + Postgres on Vercel/Neon: great DX but needs a Node host (not cPanel), adds per-service accounts and cost, and the admin console would be hand-built. Django: good, but no cPanel fit and weaker admin UX for review queues. | Low. PHP/Laravel are open source; MySQL data is portable. Scaling: stateless app, scale by adding VPS + managed DB. |
| **Admin console** | Filament (ADR-004) | Gives tables, filters, forms, roles, bulk actions, relation managers in days. Review queue with field-level diff, register, lead manager, flags UI are all Filament resources (REQ-083–095, 177–189). | Hand-built Blade admin (slower, more bugs). Nova (paid). Backpack (paid add-ons). | Medium-low: admin code is tied to Filament; domain logic stays in plain Laravel services so the admin could be replaced. |
| **Public frontend** | Blade templates, Tailwind, Alpine.js; Vite build in CI (ADR-001) | Server-rendered, no hydration cost, works at 360 px (REQ-040), indexable. All brand values come from `config/theme.php` compiled into CSS variables (REQ-052, 190). | SPA (React/Vue): worse SEO/LCP, more code. Inertia: unnecessary. | Low. |
| **Database** | MySQL 8 / MariaDB (ADR-002) | Native to cPanel. 10k–100k profiles with FULLTEXT is well within range (REQ-079). Money as integer kobo (REQ-112). | Postgres: nicer features (better FTS, row-level security) but not guaranteed on cPanel and adds a managed-DB bill. SQLite: not for concurrent writes with a queue. | Low. Avoid vendor-specific SQL so a move to Postgres/managed MySQL is a config + dump/restore. Scale: managed MySQL + read replica. |
| **Auth** | Laravel session auth + Fortify-style flows; spatie/laravel-permission for roles; Laravel policies for object-level rules; TOTP MFA (required for all staff roles, optional for owners) | REQ-047, 107–110. One login per profile owner (D-030). Server-side sessions are revocable (REQ-108). | Auth0/Clerk/Firebase: extra vendor, personal data transfer to another processor, cost at scale. Passkeys: later. | Low. |
| **Hosting** | Existing cPanel behind Cloudflare; fallback VPS (ADR-003) | Zero extra cost today. Cloudflare provides the CDN (REQ-042), WAF/rate limits (REQ-046, 122), bot challenge (REQ-081) and cache purge for immediate unpublish (REQ-209). Capability is unverified, so spike S1 decides. | Render/Fly/Railway: easy but a new monthly bill and a new processor. Managed Laravel hosts (Forge+VPS, Laravel Cloud): better, ~US$20+/mo. Held as the fallback. | Medium: shared hosting is the weakest link for uptime (REQ-045) and cannot run persistent workers. Mitigated by cron-driven queue, edge caching, and a rehearsed move to a VPS. |
| **Payments** | None (D-005). The register stores method + reference text only. | A PAN-pattern (Luhn) guard rejects card-like numbers in the reference field. | — | — |
| **Email** | Brevo SMTP; SPF/DKIM/DMARC on wiwi.africa (REQ-075, 204); bounce webhook into `email_events` | Free tier covers launch volume; deliverability is better than cPanel's own mail. Same provider for transactional and opt-in marketing mail with a one-click unsubscribe (REQ-061). | cPanel SMTP: poor deliverability. SES: cheaper at scale, needs production-access request (kept as alternative). Postmark: best deliverability, paid. | Low: standard SMTP, provider is config. |
| **File storage** | Cloudflare R2 for public images and encrypted backups; private local disk (outside web root) for claim/verification documents (ADR-007) | REQ-080: object storage behind CDN; R2 has a free tier and no egress fee. Images are re-encoded to WebP/JPEG variants at upload (REQ-042, 127). Private documents are never in a public bucket. | S3: egress cost. Local disk only: no CDN, no off-host backup. | Low (S3 API). |
| **Search** | MySQL FULLTEXT + indexed filter columns on a flattened `profile_search` table (ADR-010) | REQ-007/008/079 for ≥10k profiles. | Meilisearch/Algolia/Typesense now: another service to run and pay for. | Upgrade path via Laravel Scout driver swap. |
| **Queues / jobs** | Database queue; cPanel cron runs `schedule:run` every minute, which runs a worker with `--stop-when-empty --max-time=55` (ADR-011) | Shared hosting cannot keep a daemon alive. Jobs: email, image variants, imports, AI drafting, cache purge, retention, stale checks, backups. | Redis/Horizon: not available on shared hosting. | Move to a Supervisor worker on a VPS with no code change. |
| **AI drafting** | Anthropic API, small model, server-side, behind flag REQ-165 and an `AiDrafter` interface (ADR-009) | Claim-to-source mapping is enforced by our code, not by trusting the model (flow F3). No-training API terms and processor listing required (REQ-169). | Self-hosted model: cost and quality. Other vendors: interchangeable behind the interface. | Low: interface + stored prompt versions. |
| **Observability** | Sentry (PII scrubbed), uptime monitor on `/healthz`, directory, enquiry form and admin login (REQ-045, 050); Laravel logs with IDs only; Cloudflare Web Analytics for traffic; first-party counters for funnels (REQ-078) | Cookieless, so no consent banner needed for analytics (REQ-062). | GA4: cookies + consent + cross-border data. Plausible Cloud: good, paid ($9+). | Low. |
| **CI/CD** | GitHub Actions (ADR-012) | Matches the repo/PR workflow in CLAUDE.md. | Jenkins, CircleCI: no benefit. | Low. |
| **Bot / abuse** | Cloudflare Turnstile + Laravel throttle + Cloudflare WAF rules | REQ-081, 122. Free, privacy-friendly. | reCAPTCHA: Google cookies. | Low. |

---

## 3. System design and key flows

### 3.1 Overview

```
 Visitor ──► Cloudflare (DNS, CDN, WAF, Turnstile) ──► cPanel host
                                                        ├─ Laravel app (public pages, owner area, forms)
                                                        ├─ Filament admin + judge panels (same app, /admin, /judge)
                                                        ├─ cron: scheduler + queue worker
                                                        └─ MySQL
 Laravel ──► R2 (public images, encrypted backups)   ──► Brevo (email)   ──► Anthropic API (AI drafting, flag)
         ──► Sentry (errors, scrubbed)               ──► Cloudflare API (cache purge)
```

Modules (directories under `app/Domain/`), each owning its tables and a public service API:

`Directory` (profiles, taxonomies, search) · `Acquisition` (sources, imports, entity resolution, AI drafting) · `Review` (queue, revisions, publish) · `Trust` (claims, verification, featured, status ladder) · `Commerce` (leads, customers, arrangements, sponsors, counting rule, KPI) · `Content` (articles, pages) · `Awards` (editions, nominations, judging) · `Compliance` (DSR, takedown, suppression, kill switch, retention, consents) · `Platform` (users/roles, audit, flags, settings, theme, health).

Boundary rules, enforced by architecture tests in CI (Pest arch tests):
- `Awards` may not reference `Commerce` (REQ-199, 201, 037). There is no code path or join from commercial data to nominations, screening, shortlisting or judge views.
- `Trust` fields (Verified, Featured) may not be written from `Commerce` (REQ-184).
- Only `Review` may set a profile's published revision (REQ-208).
- Public controllers may read only through `PublicProfile` / `PublicArticle` read models (see 3.2).

### 3.2 Flow F1 — Nothing public before approval (REQ-208, 014, 015, 148, 125)

Design: **a profile never has "public fields"; it has a pointer.**

- `profiles` holds identity and state. Content lives in immutable `profile_revisions` (JSON snapshot + author + created_at + source/provenance rows).
- `profiles.published_revision_id` is NULL until a reviewer approves a revision. The **only** code that sets it is `Review\PublishRevision`, which checks: reviewer ≠ importer/drafter (REQ-148), no low-confidence or unsourced fields (REQ-143), pattern scan cleared (REQ-141), not suppressed (REQ-207), under-18 guard (REQ-068).
- Public read models (`PublicProfile`, `profile_search`, sitemap, feeds, enquiry targets) are built **only from the revision pointed to** and only where `publication_state = published`. Drafts, rejected, AI-pending, unreviewed, unpublished, removed rows never enter them.
- Owner edits create a new revision. Sensitive-field edits (name, title, organisation, awards, achievement claims) go to review; the old revision stays live until the new one is approved (REQ-015).
- Tests (REQ-208): a matrix test that, for every state, hits profile page, listing, search, sitemap, RSS/feeds, enquiry form, share/preview URL and the cache layer and asserts "not visible".

### 3.3 Flow F2 — Immediate unpublish, 410, kill switch (REQ-209, 193, 147, 207, 158, 091)

Triggers: staff unpublish, valid removal request, nominee decline, suppression add, kill switch.

1. In one DB transaction: set `publication_state` (`unpublished` or `removed`), clear/keep the pointer per reason, add suppression row if applicable, write audit log.
2. The origin returns **410 Gone + `X-Robots-Tag: noindex`** for that slug from the next request (reads DB state, not cache). Reversible unpublish restores 200.
3. After commit, dispatch a **high-priority purge job**: Cloudflare purge by URL (profile page, its listing/sitemap pages, share-image URL). Kill switch = purge everything for HTML, plus a global `kill_switch_unclaimed_individuals` flag checked in the public query scope, so even a failed purge cannot leak (the scope hides the rows at origin).
4. Public HTML is cached at the edge for at most **120 seconds** (`s-maxage=120`), listings/search for 60 seconds. Worst case if a purge call fails is 2 minutes, and the job retries with alerting.
5. Sitemap is generated from the published read model on request (cached 5 minutes) and purged by the same job. Internal search table row is deleted in the same transaction.
6. N21 (human): file search-engine removals for already-indexed URLs.

Spike S2 must prove this end to end on the real Cloudflare zone and record the measured time-to-410.

### 3.4 Flow F3 — AI drafting with claim-to-source enforcement (REQ-165–170, 142, 163)

1. Input: an approved source (REQ-137) and fetched text stored as `source_documents` with hash and retrieval date. Fetch obeys robots/terms (REQ-138); crawler identifies itself and rate-limits.
2. `AiDrafter` is called with the source text split into numbered passages and a fixed prompt version. It must return JSON: a list of claims, each `{field, text, passage_id, quote}`.
3. **Our code verifies**: `quote` must be a substring of the stored passage, field must be in the allowlist (REQ-139), the text must not match prohibited-topic patterns (REQ-167). Any failing claim is dropped; dropped claims are logged for the reviewer.
4. Surviving claims become a **draft revision** with per-field provenance rows: `source_url`, `retrieved_at`, `confidence`, `method = ai`, `source_hash`, `passage_id`, plus internal `ai_assisted`, model, prompt version (REQ-142, 168).
5. The review UI shows each claim beside its source passage. Reviewer ≠ drafter. Only then can F1 publish it.
6. Feature flag `ai_drafting` (REQ-165). The prompt never includes personal data beyond what is in the fetched source. API key held server-side only.

### 3.5 Flow F4 — Commercial register and the counting rule (REQ-021–023, 179–184, 199)

- `arrangements` store amount in integer kobo + ISO currency, method, reference text, date received, `confirmed_by`, start/end, complimentary flag, notes.
- **Status is computed, not typed** (REQ-022): a service `ArrangementState::for($row)` derives `Confirmed` only if amount, currency, method, reference, date received, confirmed_by and onboarding-started are all present and the row is not complimentary/cancelled. The dashboard counts `Confirmed AND paid`. Property-based tests cover the rule (e.g. complimentary never counts; removing a required field flips the state).
- Two thresholds (10 subscriptions, NGN 5,000,000) are settings; the dashboard shows both plus per-stream revenue (REQ-180, 181). Stream mapping is an admin-editable default per arrangement type (defaulted; the owner confirms the mapping).
- Sponsored label: set by an arrangement link or manually, applied between start and end dates by a scheduled job (REQ-116, 182). It writes only the `sponsored_*` columns; the arch test asserts it cannot touch `trust_status` (REQ-184).

### 3.6 Flow F5 — Nominations and judging behind flags (REQ-194–202)

- Flags `nominations`, `judging`, `announcements` are rows in `feature_flags`, default OFF. Turning ON requires role Admin + a text field "CEO approval reference"; both the action and the reference are audited (REQ-194).
- Nominator email is verified by a signed link; duplicates are detected on normalised nominee name + organisation + category; rate-limited per IP and per email.
- Nominee data lives in `nominations` (private). After screening, the system emails the nominee a signed link to accept / decline / correct. Decline → removal + suppression (REQ-198, 207). Public nominee pages are built from a **reviewed profile** at shortlist only.
- Judges use the `judge` Filament panel whose resources query only `Awards` tables for assigned nominations; conflicted judges are excluded at the query scope (REQ-036, 201).

---

## 4. Data model

Money = integer minor units + currency. IDs = ULID public IDs (`public_id`) plus numeric PK. All tables have `created_at`, `updated_at`; soft deletes only where noted. **[PII]** = personal data; **[SENS]** = high-sensitivity PII (identity documents, MFA secrets); **[FIN]** = commercial/financial; **[PUB]** = may appear publicly after approval.

| Table | Key columns | Notes |
|---|---|---|
| `users` | id, email **[PII]**, name **[PII]**, password_hash, email_verified_at, totp_secret **[SENS, encrypted]**, role, disabled_at | Staff and profile owners. One login per owner (D-030). |
| `roles`, `permissions`, pivots | spatie | Admin, Editor, Reviewer, Sales/Support, Finance, Judge, Editor-in-Chief, Owner. |
| `profiles` | id, public_id, type (individual/organisation/institution), slug, origin (stub/submitted/complimentary/nominee), trust_status (unclaimed/claimed/verified/featured), publication_state (draft/in_review/published/unpublished/removed/rejected), published_revision_id, owner_user_id, sponsored_by/ until, ai_assisted, last_reviewed_at, stale_at | **No personal fields live here besides slug.** Sponsored columns are separate from trust_status (REQ-024, 155). |
| `profile_revisions` | id, profile_id, number, snapshot JSON **[PII, PUB after approval]**, author_user_id, created_via (import/ai/manual/owner), approved_by, approved_at | Immutable (append-only). |
| `field_provenance` | revision_id, field_key, source_id, source_url, retrieved_at, confidence, method, source_hash, passage_ref | REQ-142. Fields with low confidence/no source blocked (REQ-143). |
| `profile_search` | profile_id, name, org, industry_id, profession_id, city_id, text (FULLTEXT) | Rebuilt from the published revision only. |
| `taxonomies` (industries, sectors, professions, org_types, countries, cities) + pivots | | REQ-009. |
| `redirects` | old_slug, profile_id | REQ-129. |
| `sources`, `source_documents` | name, type, url, access_method, licence, terms_summary, robots_checked_at, approver, status; fetched text + hash | REQ-136–138. |
| `import_batches`, `import_rows` | batch id, importer, dry_run, status; row payload, match result | REQ-092, 134. |
| `duplicate_candidates` | profile_a, profile_b, score, status | REQ-123, 146. |
| `suppression_list` | id, entity_type, name_normalised **[PII]**, identifiers (RC no., QID) , reason, added_by, added_at | Permanent (REQ-147, 207). Never purged by retention. |
| `claims` | profile_id, claimant_user_id, evidence_path **[SENS]**, method (domain_email/document), status, decided_by | REQ-152, 124. |
| `dsr_requests` | type (access/correction/removal/objection/…), requester_email **[PII]**, profile_id, status, due_at, handled_by | REQ-059, 071, 157, 158. |
| `reports` | profile_id, reporter_email? **[PII]**, reason, status | REQ-090. |
| `enquiries` | profile_id, sender_name/email **[PII]**, message **[PII]**, ip_hash, status | REQ-018, 089. Retention job. |
| `leads` | name, org, interest, contact **[PII]**, owner_user_id, status, ext_id | REQ-177, 178, 205. |
| `customers` | name, segment, contact **[PII]**, ext_id | |
| `arrangements` | customer_id, type, amount_minor **[FIN]**, currency, method, reference **[FIN]**, received_on, confirmed_by, status (computed cache), starts_on, ends_on, complimentary, stream, notes, ext_id | REQ-179. Never joined to Awards tables. |
| `arrangement_links` | arrangement_id, linkable (profile/article), applied | Sponsored label link (REQ-182). |
| `sponsors` | name, logo, url; pivots to award editions/categories/pages | REQ-186. |
| `articles`, `article_profile`, `tags` | type, title, body (sanitised Markdown), author, hero image, publish_at, sponsored_by | REQ-027–029, 026. |
| `award_editions`, `award_categories`, `criteria`, `nominations`, `nomination_stages`, `judge_assignments`, `scores`, `coi_declarations` | nominator **[PII]**, nominee link, stage, evidence **[PII]** | REQ-030–038, 196–202. Private until shortlist. |
| `feature_flags` | key, enabled, approval_ref, changed_by, changed_at | REQ-165, 193, 194. |
| `settings` | key, value JSON | Thresholds, stream map, SLA targets. Brand values are **not** here (config/theme.php). |
| `notes` | notable (morph), body **[PII maybe]**, author | REQ-187. Never exposed to Judge panel. |
| `consents` | subject_email **[PII]**, purpose, policy_version, granted_at, revoked_at | REQ-058, 061, 195. |
| `email_events` | message_id, type (sent/bounce/complaint), at | REQ-075. |
| `media` | path, variants, licence, attribution, owner | REQ-203, 162. |
| `view_counts_daily` | profile_id, date, count | REQ-019, Q23 (bots filtered; no IPs stored). |
| `audit_log` | id, at, actor_id, action, subject_type/id, old JSON, new JSON, prev_hash, hash | Append-only, hash-chained (REQ-051, 188). Nightly copy to R2. |
| `processors` | name, purpose, location, dpa_ref | REQ-065. |

PII handling: columns marked PII/SENS are listed in `config/pii.php` (single source), which drives the retention job (REQ-063), the access export log (REQ-069), log scrubbing, and the DSR export.

---

## 5. API / module outline and auth

There is **no public JSON API** in the MVP. Pages are server-rendered; forms post to the same app. This keeps the attack surface small. Admin is Filament (Livewire).

| Area | Routes (outline) | Auth |
|---|---|---|
| Public site | `GET /`, `/about`, `/people`, `/businesses`, `/institutions`, `/industries/{slug}`, `/stories`, `/stories/{slug}`, `/awards`, `/awards/{edition}/{category}`, `/p/{slug}`, `/search`, `/sitemap.xml`, `/robots.txt`, legal pages, `/how-we-compile` | none; HTML cached ≤120 s |
| Public forms | `POST /subscribe` (lead), `POST /contact`, `POST /p/{slug}/enquire`, `POST /p/{slug}/report`, `POST /p/{slug}/correct`, `POST /p/{slug}/remove`, `POST /nominate` (flag), `POST /newsletter` | none; Turnstile + throttle (per IP and per email) + validation (Form Requests) |
| Claim | `GET/POST /p/{slug}/claim` | email-verified user |
| Nomination links | `GET/POST /nominee/{signed}`, `/nominator/verify/{signed}` | signed URL, single use, expiry |
| Owner area | `/account`, `/account/profile` (edit, preview, history, view count), delete account | session, `owner` role, policy `ProfilePolicy` (own profile only) |
| Auth | `/register`, `/login`, `/logout`, `/forgot-password`, `/email/verify`, `/two-factor` | guest/session; login throttled; staff MFA required |
| Admin panel | `/admin/*` Filament resources: Profiles, Review queue, Duplicates, Sources, Imports, Suppression, Claims, DSR/Takedown, Reports, Leads, Customers, Arrangements, Sponsors, Articles, Taxonomies, Awards (edition/categories/nominations/judges), Flags, Settings, Users, Audit log, KPI dashboard | session + MFA + role/permission per resource; policies per record; destructive actions confirm + reason + audit |
| Judge panel | `/judge/*` assigned nominations and scoring | `judge` role + MFA; query scope = assigned and not conflicted; no Commerce access |
| Health | `GET /healthz` (DB, queue heartbeat, disk) | none; returns no detail; token for verbose |
| Webhooks | `POST /webhooks/brevo` (bounces) | HMAC/shared secret; idempotent |
| Scheduled jobs | kill-switch purge, sponsored apply/expire, expiry reminders, stale checks, retention, backup, view-count roll-up, SLA breach flags | CLI only |

Authorisation rules: deny by default; every route group names its guard and role; every record access goes through a Policy; Filament resource `canViewAny` etc. map to permissions; tests assert 403 for each role × resource.

---

## 6. Security

### 6.1 Threat model (top threats and controls)

| Threat | Control |
|---|---|
| Wrong or defamatory facts published about a real person | F1 (pointer, second reviewer), per-field provenance, neutral checklist, F3 claim verification, corrections log, takedown SLA, F2 immediate unpublish |
| Unreviewed content leaks (cache, search, sitemap, preview) | F1 read models + REQ-208 matrix test; no preview URLs without admin auth |
| Removed content stays visible | F2: 410 at origin, purge, 2-minute worst-case edge TTL |
| Staff account takeover | Mandatory TOTP MFA, strong passwords + breach-list check, login throttle, session revocation, admin on a separate path with Cloudflare rule for optional IP/country limits |
| Fraudulent claim of someone else's profile | Domain-email or document proof, manual approval, ownership dispute process (REQ-124), audit |
| Pay-to-win leakage | Arch tests: Awards ↛ Commerce, Commerce ↛ Trust writes; judge queries scoped |
| Spam / abuse of forms and enumeration | Turnstile, throttle, duplicate detection, signed single-use links, uniform responses for email existence |
| XSS / injection | Blade auto-escaping; user rich text limited to sanitised Markdown (allowlist); parameterised queries only; CSP header; no `{!! !!}` on user data (lint rule) |
| Malicious uploads | Allowlist image types for public uploads, **re-encoded** server-side (strips payload/EXIF); claim documents only JPG/PNG/PDF, stored privately, size-limited, scanned (ClamAV if S3 spike allows, else hosted scan); never executed or served inline |
| SSRF / crawler abuse in ingestion | Fetcher accepts only URLs of Approved sources (host allowlist), blocks private IP ranges, size and time limits, identifies itself, obeys robots |
| Secrets exposure | See 6.3 |
| Prompt injection via fetched pages | Model output is data, not commands; no tools given to the model; every claim verified against stored passage; reviewer sees source |
| Insider misuse / data export | Role-based exports, export log (REQ-069), audit log, least privilege |

### 6.2 Authn / authz
- Staff: email + password + TOTP; recovery codes; sessions revocable; idle timeout 60 min for admin.
- Owners: email verification, optional MFA; one login per profile (D-030).
- Judges: invited, MFA required, can see only assigned and unconflicted nominations (REQ-036, 201).
- Roles map to Filament permissions; sensitive actions (flags, kill switch, suppression removal, exports) are limited to named roles (REQ-193, 194).

### 6.3 Secrets
- Real values exist only in: the server's `.env` (outside web root, mode 600, created by you in cPanel/SSH, N18) and GitHub **Environment** secrets (staging, production) used by the deploy job.
- cPanel has no secret manager; the `.env` file is the "host secret store". Deploys never overwrite it.
- `.env.example` lists names only. Agents never read `.env*` (CLAUDE.md). Gitleaks runs in CI and as a pre-commit hook.
- Keys: Brevo, Cloudflare API token (zone cache purge only), Turnstile, R2 (separate tokens for media vs backups), Sentry, Anthropic, backup encryption passphrase, `APP_KEY`.
- Rotation is documented in the runbook; Cloudflare token scoped to *Cache Purge* on one zone.

### 6.4 PII and payment handling
- No card or bank account data: register holds method + reference text; a Luhn/long-digit guard blocks card-like numbers.
- PII columns are catalogued (`config/pii.php`), excluded from logs and Sentry (scrubber), encrypted at rest where sensitive (TOTP, claim documents), and covered by retention, DSR export and erasure (REQ-059, 063).
- Stubs follow the REQ-139/140 allowlist: the schema has no column for forbidden attributes; free text is pattern-scanned (REQ-141).
- Processor register and DPAs: hosting, Cloudflare, Brevo, Sentry, R2, Anthropic (REQ-065, 169).
- Breach procedure in the runbook (REQ-066).

### 6.5 Validation, rate limits, dependencies, logging, backups
- Validation: Form Request per endpoint; server-side only trust; length limits; Unicode-safe names (REQ-053).
- Rate limits: throttle middleware per IP and per account/email for each public form (REQ-122); Cloudflare rate-limiting rule on `/login` and `/subscribe`.
- Dependencies: Dependabot weekly, `composer audit` and `npm audit` fail CI on high/critical, lockfiles committed, minimal packages, no abandoned packages, Renovate-style grouped PRs reviewed by the reviewer agent.
- Headers: HSTS, CSP (nonce), X-Content-Type-Options, Referrer-Policy, Permissions-Policy, frame-ancestors none.
- Logging: structured JSON, IDs not names/emails, request ID, 30-day retention; audit log separate and kept.
- Backups (REQ-049): nightly `spatie/laravel-backup` (DB + private files) encrypted and sent to a separate R2 bucket; cPanel account backup as a second layer; **restore test** into staging before launch and monthly (RPO 24 h, RTO 8 h).
- Audit-log immutability on shared hosting: append-only model (no update/delete methods), hash chain, DB triggers blocking UPDATE/DELETE if the account has TRIGGER privilege (checked in S1), and nightly off-host copy so tampering is detectable.

### 6.6 Legal exposure (not legal advice)
Publishing personal data without consent (D-013) is the largest exposure (NDPA 2023, defamation, false light). Architecture cannot remove it but limits it: 100% human review, per-field sources, neutral-content rules, AI claim verification, 2-day removal SLA, permanent suppression, kill switch, 410/purge. Legal's written position is outstanding (Q5). The AI provider is a cross-border processor: Legal must confirm transfer terms before `ai_drafting` is switched on in production.

---

## 7. Testing strategy and CI gates

Tests are written first (CLAUDE.md). Test names cite REQs (e.g. `it('REQ-208 hides unreviewed profiles from sitemap')`).

| REQ type | Test approach |
|---|---|
| Visibility / publication (208, 209, 193, 147, 207) | Feature-test matrix over every surface × every state; F2 test with a faked Cloudflare client asserting purge calls; spike S2 for real-world latency |
| Authorisation (083, 109, 201, 037) | Role × resource 403/200 matrix; judge-scope tests; arch tests for module boundaries |
| Business rules (022, 023, 179–184, 112) | Unit + property tests for counting rule, money, label separation |
| Data acquisition (132–176) | Import dry-run tests with fixtures; field allowlist tests; provenance required; duplicate detection; fake fetcher; robots/terms enforcement |
| AI (165–170) | Fake `AiDrafter`: unsupported claim dropped, prohibited topic dropped, quote must match passage; no live API in CI |
| Forms / abuse (018, 081, 122, 196) | Feature tests for validation, throttling, Turnstile fake, signed-link expiry |
| Privacy (057–072) | DSR workflow tests, retention job tests, PII catalogue completeness test (every PII column is catalogued) |
| UI / a11y / perf (040–043, 055) | Playwright e2e on Chromium + WebKit mobile profile, axe-core on key pages, Lighthouse CI budget (LCP, CLS) on staging |
| Security (046) | Larastan, `composer audit`, `npm audit`, Gitleaks, weekly OWASP ZAP baseline on staging, header test |
| Theme (190) | CI check that fails if brand colours/fonts appear outside `config/theme.php` |

**CI gates on every PR (all required):** Pint (style) → Larastan level ≥ 6 → Pest unit/feature + arch tests with coverage threshold for `Domain/` → Vite build → Playwright smoke + axe → dependency audit → Gitleaks → PR must cite REQ IDs and `Closes #N`. A separate reviewer agent/step marks work done.
**Deploy gates:** merge to `main` auto-deploys to staging after smoke tests; production deploy needs a manual approval on the GitHub `production` environment.

---

## 8. Deployment

| Item | Design |
|---|---|
| Environments | Local (Docker not required; `php artisan serve` + MySQL), **staging** (staging.wiwi.africa, public and indexable per D-029, real data only after approval), **production** (wiwi.africa). Two cPanel subdomain apps/databases. |
| Accounts to open (N18) | **Cloudflare** (free; wiwi.africa zone), **GitHub Actions secrets/environments**, **Brevo** (email), **Cloudflare R2** (media bucket + backups bucket), **Sentry** (free), **Uptime monitor** (Better Stack or UptimeRobot free), **Anthropic API** (billing, no-training terms; only when AI flag is planned), **cPanel**: PHP 8.3 selector, SSH key access, two MySQL DBs + users, cron jobs, `.env` files. Optional: Google Search Console (needed for N21). |
| Build | CI builds `vendor/` (`--no-dev --optimize-autoloader`) and Vite assets; packages a release tarball with a version stamp. |
| Release | Upload to `releases/<timestamp>/` via SSH/rsync, symlink `shared/.env` and `shared/storage`, `php artisan migrate --force`, `config:cache route:cache view:cache`, atomic `current` symlink switch, warm cache, run smoke check. If SSH is unavailable, fallback is cPanel *Git Version Control* with `.cpanel.yml` copying the CI-built artifact branch. |
| Config / secrets | Section 6.3. Config comes from `.env`; code never calls `env()` outside `config/*`. |
| Migrations | Forward-only, **expand/contract** (additive first, remove later) so the previous release still runs against the new schema. Only the backend area writes migrations. |
| Rollback | Switch `current` symlink to the previous release (seconds). DB rollback is by restoring a pre-deploy dump taken automatically before each production migration. |
| Monitoring | `/healthz` + uptime checks on directory, enquiry form, admin login (REQ-045); Sentry; alert to email/phone; failed-job and purge-failure alerts; backup-success heartbeat. |
| Runbook | `docs/runbook.md` (REQ-095): deploy, rollback, restore, incident, kill switch, key rotation, move-to-VPS. |

**Fallback hosting (if S1 fails):** a single small VPS (2 vCPU / 4 GB) in an EU or Africa region with Caddy/Nginx + PHP-FPM + MySQL + Supervisor, deployed with the same release-directory script. Same code, same CI.

---

## 9. Cost (monthly, estimates to verify at purchase)

| Item | Now (≈100+100 profiles, <20k views) | At 10× (≈1M views/mo, ≈10–20k profiles, 2–3 staff) |
|---|---|---|
| cPanel hosting (already bought) | US$0 extra | Replace with VPS or managed app: US$20–60 |
| Domain wiwi.africa | already paid (annual) | same |
| Cloudflare | Free | Free, or Pro ~US$20 for better WAF/analytics |
| R2 storage + backups | ~US$0–1 | ~US$2–5 |
| Brevo email | Free (300/day) | ~US$9–25 |
| Sentry | Free | ~US$26 |
| Uptime monitoring | Free | Free–US$10 |
| AI drafting (small model, a few hundred profiles) | ~US$5–20 | ~US$20–100 depending on volume |
| Managed DB / search (if needed) | — | US$15–40 DB, US$0–30 Meilisearch |
| **Total** | **≈ US$5–25** | **≈ US$100–280** |

Biggest cost drivers are people (reviewers), not hosting.

---

## 10. Parallelism plan

### 10.1 Areas and directory ownership

Rule: an agent edits only its area's paths. Cross-area needs become an issue for the owning area. Migrations are written only by `backend` (timestamp-ordered, one table-group per PR) to avoid conflicts; each module has its own route file.

| Area | Owns | Notes |
|---|---|---|
| **backend** | `app/`, `database/`, `routes/`, `config/` (except theme), `lang/`, `tests/Feature`, `tests/Unit`, `tests/Arch` | Domain services, models, policies, jobs, Filament resources (`app/Filament`), mail classes |
| **frontend** | `resources/views/` (except `resources/views/emails` and `vendor/filament`), `resources/css`, `resources/js`, `config/theme.php`, `public/` assets, `vite.config.js`, `tailwind.config.js`, `tests/e2e` for UI specs | Blade pages, components, theme tokens, a11y, SEO tags |
| **infra** | `.github/`, `deploy/`, `scripts/`, `docs/runbook.md`, `.env.example`, `docker/` (if any), `composer.json` scripts section | CI, deploy, backups, monitoring, Cloudflare/Brevo setup docs |
| **qa** | `tests/e2e/`, `tests/Visibility/`, `tests/Security/`, `docs/traceability.md`, `docs/verify-*.md` | Independent reviewer/verifier; does not write feature code |

Interface contracts between areas (so they can work in parallel): backend exposes view-models via controllers (`resources/views` receives typed arrays documented in `docs/contracts/` per page); frontend builds against fixtures; infra owns the `deploy` command and CI job names.

### 10.2 Foundation (serial, M0 — must be built first, in this order)

1. **Scaffold**: Laravel project, PHP 8.3, Pest, Pint, Larastan, Vite/Tailwind, Filament install, directory layout, composer scripts. (infra+backend)
2. **CI**: GitHub Actions with all gates from section 7 (empty test suites pass), Dependabot, Gitleaks. (infra)
3. **Deploy to staging**: release-directory script, `.env` on server, `/healthz`, cron wiring, Cloudflare in front. Includes spikes S1 and S2. (infra)
4. **Platform core**: users/roles/permissions, MFA, `AuditLogger` + `audit_log` (hash chain), `Flags` service, settings, `config/pii.php`. (backend)
5. **Core data**: `profiles`, `profile_revisions`, `field_provenance`, taxonomies, suppression list, **`PublishRevision` as the single choke point and the `PublicProfile` read model with the REQ-208 matrix test**. (backend)
6. **Theme + layout shell**: `config/theme.php` → CSS variables, base layout, components, theme-leak CI check. (frontend)
7. **Arch tests** for module boundaries (Awards ↛ Commerce, Trust writes, publish choke point). (backend/qa)
8. **Cache/purge service** with a fake and the 410 middleware (F2 skeleton). (backend)

After M0, the following can run in parallel waves (the plan will split them): Directory pages + search; Review queue; Acquisition imports + source registry; AI drafting; Commerce register + KPI dashboard; Content/articles; Claims/DSR/takedown; Awards (flagged); Owner area + intake; Email templates; Analytics/consent; Runbook/backups.

---

## 11. Top 5 risks and spikes

| # | Risk | Spike / mitigation |
|---|---|---|
| 1 | **The cPanel account may not meet the needs** (PHP 8.3, SSH, Composer, cron every minute, MySQL version, TRIGGER privilege, outbound HTTPS to APIs, resources, region, uptime). | **S1 (half a day, you + infra agent):** run a checklist script on the account (`scripts/preflight-host.sh`) and record results in Q26. Fail → VPS fallback. |
| 2 | **Immediate unpublish may not be immediate** (CDN caches, indexed pages). | **S2:** prove 410 + purge-by-URL on the real Cloudflare zone, measure time-to-410, test kill-switch purge-everything and failure retry. |
| 3 | **AI + non-consensual individual profiles** (legal and factual harm). | **S3:** prototype F3's quote-verification on 20 real source pages with the actual model; measure drop rate and false-accept rate; reviewers sign off before the flag is used. Keep `ai_drafting` OFF in production until Legal confirms the transfer. |
| 4 | **Upload scanning and malware on shared hosting** (ClamAV may be unavailable). | **S4:** test ClamAV availability; otherwise restrict claim documents to image/PDF with re-encode/flatten and use a hosted scan API or manual-only review. |
| 5 | **Email deliverability and domain setup for wiwi.africa** (SPF/DKIM/DMARC, .africa DNS on Cloudflare, bulk nominee mail). | **S5:** set up Brevo + records on staging subdomain, send to Gmail/Outlook/Yahoo and a Nigerian provider, inspect headers; warm up before bulk. |

Other watch items: shared-host uptime vs the 99.5% target (REQ-045); review capacity (REQ-206); Legal's position (Q5).

---

## 12. ADRs

One short record per decision in `docs/adr/`: ADR-001 framework, 002 database, 003 hosting and edge, 004 admin console, 005 publication model, 006 caching and unpublish, 007 media and file storage, 008 email, 009 AI drafting, 010 search, 011 queues and scheduling, 012 CI/CD and deploy, 013 audit log.
