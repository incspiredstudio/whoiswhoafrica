# WHOISWHO — Requirements

Derived from `docs/spec.md` (Venture Brief, sprint 4 Nov 2026 – 31 Jan 2027). Written 2026-11-06; updated after Gate 1 answers (see `docs/decisions.md`). **Brief v2 (2026-11-09, D-052):** `docs/spec.md` is now `WIWI - VENTURE BRIEF (1).md`, the most current brief; its name text "WIWI" is not adopted, the brand stays WHOISWHO.

**Scope change (D-005):** the website processes no payments. Customers pay offline; admins record payments in a commercial register. All payment/checkout requirements are marked **Withdrawn** (numbers kept): REQ-011, 048, 070, 073, 074, 076, 097, 099, 113, 114, 117, 118, 119.
**Q7/Q8/C13 resolved (D-028):** there are no tiers, packages or entitlements. Every listing has the same format. Commercial relationships are admin-recorded arrangements. Withdrawn for this reason: REQ-010, 012, 017, 185.
**Q1 correction (D-029):** the demo site is public and indexable; profiles are visible only after human approval; removals return 410/noindex (REQ-208, 209).
**Q17 answered (D-030):** one login per profile owner; REQ-111 withdrawn for now.
**D-033 as amended (2026-11-08):** the acquisition pipeline never publishes. Records are imported into WordPress; organisations go live on import, labelled and noindex; individuals and entrepreneurs are not public until a reviewer approves. REQ-148 and REQ-208 are amended; section 12 is rewritten; REQ-237 and REQ-238 are added in section 14. Requirements marked "Conflict with D-033" are unchanged until you rule.
**No public accounts (D-034, 2026-11-08):** no login or signup for anyone on the public site. Only staff use wp-admin (two-factor required). Judges have no access for now. Withdrawn: REQ-019, 035, 107, 110, 128, 201. Amended: REQ-013, 015, 018, 036, 037, 047, 060, 083, 108, 109, 122, 152, 160, 173, 175, 196, 197, 200. New: REQ-229 to REQ-236 in section 13.
**Wireframe review (D-035 to D-041, 2026-11-08):** no directory-size target (D-035); no claiming and no Claimed or Unclaimed labels (D-036); the only flag is Nominations (D-037); no source registry in the website admin (D-038); controlled lists are WordPress taxonomies (D-039); corrections, removals and reports land in the Support inbox (D-040); menus by role (D-041). Withdrawn: REQ-124, 151, 152, 173, 230, 231. Amended: REQ-009, 015, 018, 038, 069, 088, 089, 094, 104, 132, 136, 137, 138, 150, 156, 160, 165, 174, 181, 191, 193, 194, 206, 227, 232, 233, 235, 236. New: REQ-239 to REQ-241.
**Sources, kill switch and roles (D-042 to D-044, 2026-11-08):** a read-only Sources screen and a filterable source field (REQ-242, 243); the kill switch is replaced by bulk unpublish by filter (REQ-193; REQ-227 withdrawn); only the roles Reviewer, Editor and Administrator exist, plus the anonymous Visitor (REQ-083, 235, 239, 240). "Finance" in REQ-086, 115 and D-018 is the offline business function, not a system role.
**Rulings (D-045, D-046):** bulk unpublish by filter is for the Administrator and Reviewer; the Administrator alone confirms payments; staff edits go live on one person's sign-off (accepted); capabilities can be granted per user across roles (REQ-244).
**Defaults accepted (D-047):** every question that was Open now carries its default as the rule, except Q16 which is waiting on the team lead.
**Pending (team lead):** Q16.

**Priority key:** `M` = MVP, built as early as possible (dates in the brief are ceilings, not targets) · `M (flag)` = built in the MVP but behind the Nominations feature flag (the only flag, D-037) that is OFF until the Venture CEO approves · `S` = should · `L` = later (post-sprint) · `Q#` = scope depends on that open question.
**Scope key:** `SITE` = the website and WordPress. `ACQUISITION` = the pipeline and its data. `BOTH` = binds both sides, or crosses the contract in `docs/contracts/import-format.md`. Numbers never change.
"Source" = spec section. `—` means not stated in the spec (inferred; see group 7).

---

## 0. Classification of the spec

The spec is mostly a **business, brand and sales plan**. Software is a minority of it.

| Spec section | Type | Notes |
|---|---|---|
| 1, 2, 3 Intro, brand, values | Non-software | Shapes copy, design, editorial rules. |
| 4 Audiences | Input | Drives roles and profile types. |
| 5.1 Profiles, 5.2 Directory, 5.3 Editorial | **Software** | Core build. |
| 5.4 Awards | **Software** | Full workflow built behind feature flags (Q12). Rules and governance are non-software. |
| 5.5 Events | Non-software | Tickets, tables, venues — manual for now. |
| 5.6 Intelligence | Out of scope | Spec says "not a requirement for the first three-month sprint". |
| 6 Revenue streams | Mixed | Payment is offline. Software = commercial register, Sponsored/Featured labels (6.1, 6.3); 6.2, 6.4, 6.5 = sales/ops. |
| 7 Maiden edition | Mixed | Categories, judging panel, event = non-software; nomination flow = software. |
| 8 Sprint plan | Timeline | Source of date-driven requirements. |
| 9 Target / 9.3 counting rule | Mixed | Commercial register + counting rule = software. |
| 10 Go-to-market | Non-software | Only SEO touches the product. |
| 11 Brand voice | Non-software | Copy guidelines. |
| 12 Identity & website | **Software** | Information architecture, pages, journeys. |
| 13 Org & shared services | Non-software | Tells us Incspired provides dev/hosting/CRM (CaromHQ). |
| 14 Metrics | Mixed | Dashboard inputs are software; the dashboard itself may be a spreadsheet/CRM. |
| 15 Principles | Ops | 15.5 requires records in CaromHQ. |
| 16, 17 Risks, success | Input | Success list reused as acceptance. |
| 18 Next stage | Later | Verification features, expansion. |

---

## 1. Functional

| ID | Requirement | Source | Pri | Scope |
|---|---|---|---|---|
| REQ-001 | The public site provides pages: Home, About, Discover People, Discover Businesses, Discover Institutions, Industries, Awards/Maiden Edition, Stories, Subscribe/Get Featured, Nominate, Contact. | 12.2 | M | SITE |
| REQ-002 | The home page states what the brand is (name from the theme config), who it is for, and how to get listed, featured, nominated or recognised, without scrolling past the first screen on mobile. | 12.3 | M | SITE |
| REQ-003 | The system supports individual profiles with: full name, title, industry/expertise, biography, career history, achievements, education, affiliation, social/professional links, media appearances, awards, enquiry option. | 5.1 | M | BOTH |
| REQ-004 | The system supports business profiles with: name, industry/sector, description, founding year, HQ and locations, leadership, products/services, achievements, website/social, business contact, media features. | 5.1 | M | BOTH |
| REQ-005 | The system supports institutional profiles (education, associations, public bodies, NGOs) as a distinct type with the business fields plus mandate/type and a longer history section. | 5.1 | S | BOTH |
| REQ-006 | Every published profile has a stable public URL, unique slug, page title/meta description, Open Graph tags and schema.org (Person/Organization) markup, and is listed in the sitemap. *(D-033: pending-review profiles have no sitemap entry or schema.org markup; REQ-217.)* | 5.3, 12 | M | SITE |
| REQ-007 | The directory can be browsed and filtered by industry, profession, sector, country, city, organisation type, professional category, achievement/recognition and award category. | 5.2 | M (industry, type, country, city, profession); S (rest) | SITE |
| REQ-008 | Visitors can search profiles by name, organisation or keyword and get ranked results. | 5.2 | M | SITE |
| REQ-009 | The controlled lists (industries, sectors, professions, organisation types, countries, cities) are custom WordPress taxonomies managed in WordPress's native term screens, hierarchical where useful (cities under countries). Administrators and Editors add and edit terms; Reviewers can only assign existing terms to a profile. Places are one hierarchical taxonomy (country > city; Q78, accepted by default). The country taxonomy is Nigeria-first and includes other African countries and pan-African bodies. *(Amended by D-039.)* | 5.2 | M | BOTH |
| REQ-010 | **WITHDRAWN** (no tiers or packages; every listing has the same format; D-028). ~~An informational packages page describes each package, its inclusions and indicative pricing as admin-editable content. It has no cart and no checkout, and its call to action leads to the enquiry form (REQ-177).~~ | 6.1, 8 P1 | Withdrawn | SITE |
| REQ-011 | **WITHDRAWN** (no checkout; replaced by lead form REQ-177). ~~A prospective subscriber can choose a plan, create an account, pay and submit their profile in one continuous journey with no human intervention required.~~ | 12.3 | Withdrawn | SITE |
| REQ-012 | **WITHDRAWN** (no packages; D-028). ~~A corporate/institutional customer record can cover several profiles and users (configuration-driven; details pending Q7).~~ | 6.2 | Withdrawn | SITE |
| REQ-013 | The profile intake form validates input, saves drafts and accepts image uploads with enforced type and size limits. Customers reach it through a single-use signed link (REQ-233) emailed by staff after the offline agreement: no account, no password, no payment step. The draft is held against the link until submitted or expired. Staff may instead enter the profile for the customer (REQ-085). *(Amended by D-034.)* | 8 P1 | M | SITE |
| REQ-014 | A submitted profile enters an editorial review queue and is not public until a staff reviewer approves it. | 8 P2, 16 | M | SITE |
| REQ-015 | A profile owner asks for a change to a published profile through the public correction form (REQ-232). Staff apply it by editing the profile directly in wp-admin; the edit is written to the audit log and version history automatically (REQ-125, 188). There is no owner login and no owner self-edit. *(Amended by D-034, D-040.)* | 6.1 | M | SITE |
| REQ-016 | Admins can mark any post or profile Sponsored (REQ-182). The label is distinct from any trust status (REQ-024). There is no paid placement tier. | 6.1, 3.4 | M | SITE |
| REQ-017 | **WITHDRAWN** (no paid tiers; every listing has the same format; D-028). ~~Paid tiers allow additional images/media on the profile.~~ | 6.1 | Withdrawn | SITE |
| REQ-018 | A visitor can send an enquiry to a profile owner through a relay form; the owner's email/phone is not exposed; enquiries are rate-limited and spam-protected; a profile owner can ask staff to switch enquiries off, and staff do it in wp-admin (logged). Enquiries land in the Support inbox (REQ-089); staff forward them to a contact address held for the profile where one exists. *(Amended by D-034, D-036.)* | 5.1 | M | SITE |
| REQ-019 | **WITHDRAWN** (D-034: no owner area. Owners ask staff for the view count (REQ-236).) ~~A profile owner can see their profile status and view count.~~ | 6.1 | Withdrawn | SITE |
| REQ-020 | Profile analytics beyond view count (sources, enquiries over time). | 6.1 | L | SITE |
| REQ-021 | The system keeps an admin-managed commercial register (see REQ-179). | 9.3 | M | SITE |
| REQ-022 | Counting rule (brief section 9.3): a "paid subscription" for the sprint target is a paid arrangement with status Confirmed in the register. Confirmed requires amount, currency, payment method and reference, date received, a named confirmed-by admin, and onboarding started. Status is computed from fields, not edited by hand. Complimentary arrangements never count. | 9.3 | M | SITE |
| REQ-023 | Complimentary arrangements carry a flag, are excluded from paid counts and revenue, and are reported separately. | 9.3 | M | SITE |
| REQ-024 | Each profile shows exactly one trust status (see the status ladder in REQ-150). Sponsored, Award and Editorial markers are separate labels and never imply verification. | 3.4, 5.4 | M | SITE |
| REQ-025 | Verification follows a documented checklist per entity type; the system records who verified, when, and what evidence; payment status is never an input. | 3.4, 6.1 | M | SITE |
| REQ-026 | Sponsored articles and campaigns carry a visible "Sponsored" label on the page, listing cards and shares. | 6.3 | M | SITE |
| REQ-027 | Editors can create, schedule and publish articles (interview, feature, spotlight, opinion, announcement, news) with title, body, author, hero image, tags and SEO fields. | 5.3 | M | SITE |
| REQ-028 | Articles can be linked to profiles and industries; a profile page lists its related articles. | 5.3 | S | SITE |
| REQ-029 | A Stories page lists articles filterable by type and industry. | 12.2 | M | SITE |
| REQ-030 | A Nominate page collects: nominator identity, nominee identity, category, supporting evidence, and a conflict-of-interest declaration. | 7.4, 12.2 | M | SITE |
| REQ-031 | Staff can list, filter, export and set the status of nominations. | 7.4 | M | SITE |
| REQ-032 | The Awards section shows the edition, categories, eligibility criteria and the nomination/judging process, managed as content. | 5.4, 7.3 | M | SITE |
| REQ-033 | Category, nominee and winner pages exist; nominee pages appear only at the shortlist stage (REQ-198). | 12.2 | M | SITE |
| REQ-034 | The system tracks each nomination through the 10 stages of the recognition process: screening, shortlist, judging, final approval, notification, announcement. | 7.4 | M | SITE |
| REQ-035 | **WITHDRAWN** (D-034: judges have no accounts; scores are entered by staff (REQ-234).) ~~Judges have accounts and score assigned nominations against configurable criteria and weights (REQ-200).~~ | 5.4 | Withdrawn | SITE |
| REQ-036 | Conflicts of interest are recorded per judge and nomination (declared by the judge offline, entered by an Editor). A score cannot be recorded for a judge in conflict with that nomination, and conflicted staff cannot screen it. *(Amended by D-034.)* | 5.4 | M | SITE |
| REQ-037 | Score-entry and results screens, and any export of them, never display payment, subscription, sponsorship or customer-record data of any nominee or nominator (see REQ-201, REQ-234). *(Amended by D-034.)* | 5.4, 7.4 | M | SITE |
| REQ-038 | Winner notification and public announcement can be prepared and published from the system through the normal publish step. There is no feature flag for announcements. *(Amended by D-037.)* | 7.4 | M | SITE |
| REQ-039 | A Contact page sends messages to a staff inbox and logs them. | 12.2 | M | SITE |

## 2. Non-functional

| ID | Requirement | Source | Pri | Scope |
|---|---|---|---|---|
| REQ-040 | All public pages are responsive and usable at 360 px width. | 12.1 | M | SITE |
| REQ-041 | Profile and directory pages reach Largest Contentful Paint ≤ 2.5 s on a mid-range Android phone over throttled 4G. *(assumed target)* | 12 | M | SITE |
| REQ-042 | Images are served resized and in modern formats via a CDN. | 12.1 | M | SITE |
| REQ-043 | Public pages meet WCAG 2.1 AA. *(assumed target)* | 12 | M | SITE |
| REQ-044 | Public content is server-rendered or static so it is indexable; canonical URLs and sitemaps are provided. *(D-033: pending-review pages are noindex; REQ-217.)* | 5.3 | M | SITE |
| REQ-045 | **AMENDED 2026-11-09 (D-050).** Production availability ≥ 99.5% monthly. Monitoring with alerting of the enquiry form, directory and admin sign-in is added when an uptime monitor is set up (later, optional). *(assumed)* | — | M | SITE |
| REQ-046 | All traffic is HTTPS; OWASP Top-10 mitigations, security headers, form rate limits and dependency scanning are in place. | — | M | SITE |
| REQ-047 | All human staff accounts require strong passwords and two-factor authentication, enforced before first use of wp-admin, with recovery codes and lockout after repeated failures. Only staff have accounts (REQ-229). *(Amended by D-034.)* | — | M | SITE |
| REQ-048 | **WITHDRAWN** (no card handling on the site; PCI scope removed). ~~Card data never touches WHOISWHO servers (hosted checkout / tokenised).~~ | — | Withdrawn | SITE |
| REQ-049 | **AMENDED 2026-11-09 (D-050).** Database and uploads are backed up daily (cPanel backups, plus a plugin or export if needed); a restore is tested before launch; RPO 24 h, RTO 8 h. No separate bucket is required. *(assumed)* | — | M | SITE |
| REQ-050 | Errors, uptime and form-submission failures are monitored; logs contain no personal data beyond IDs. | — | M | SITE |
| REQ-051 | All staff/admin actions on profiles, customer records and reviews are written to an immutable audit log. *(D-033: imports too; REQ-228.)* | 15.7 | M | SITE |
| REQ-052 | **AMENDED 2026-11-09 (D-052).** Brand name (WHOISWHO), long-form name (Who Is Who In Africa), edition name, legal trading name (whoiswhoafrica), colours, type and the tagline "Influence. Achievement. Legacy." are implemented as design tokens so the approved identity can be applied or changed without rebuilding pages. | 2.2, 12.1 | M | SITE |
| REQ-053 | The site is English-only, handles names with diacritics (UTF-8), uses NGN formatting and the WAT time zone. | 7.2 | M | SITE |
| REQ-054 | The design handles ≥ 50 concurrent users and ≥ 100k page views/month on the launch infrastructure. *(assumed)* | — | M | SITE |
| REQ-055 | Supports the latest two versions of Chrome, Safari, Edge, Firefox and Android Chrome. | — | M | SITE |
| REQ-056 | A staging environment mirrors production; automated tests run on every PR. | — | M | SITE |

## 3. Data & privacy

*Nigeria Data Protection Act 2023 (NDPA) is assumed to apply; exact duties must be confirmed by Legal (Q5). This is not legal advice.*

| ID | Requirement | Source | Pri | Scope |
|---|---|---|---|---|
| REQ-057 | For each category of person whose data is held the lawful basis is recorded. For public figures published without consent the basis is legitimate interests with a right to object; Incspired Legal's written position is requested by 13 Nov and is not a build blocker (D-016). | 13.2 | M | BOTH |
| REQ-058 | A privacy notice is shown at every point of collection; consent (where used) is stored with timestamp and policy version. | 12.2 | M | SITE |
| REQ-059 | The system supports data-subject requests (access, correction, erasure, objection, portability) with intake, tracking and due dates. | — | M | BOTH |
| REQ-060 | Profile owners choose, by asking staff, which contact details are public; the default is none beyond the enquiry form. *(Amended by D-034.)* | 5.1 | M | SITE |
| REQ-061 | Marketing email requires separate opt-in and every message has one-click unsubscribe. | 10.5 | M | SITE |
| REQ-062 | Non-essential cookies/analytics load only after consent. | — | M | SITE |
| REQ-063 | A retention schedule exists per data class and a job deletes or anonymises data when it expires. | — | S | BOTH |
| REQ-064 | Nominator details are never public. Nominee data stays private until eligibility screening is complete; see REQ-197 and REQ-198. | 7.4 | M | SITE |
| REQ-065 | A register lists every processor and its location (hosting, email, and any analytics or AI provider in use), each with a data-processing agreement. | — | M | BOTH |
| REQ-066 | A breach-response procedure exists (detect, log, notify the regulator and affected people within the legal deadline). | — | M | BOTH |
| REQ-067 | The system does not collect or store sensitive personal data (health, religion, politics, ethnicity, biometrics, criminal records, sexual life, trade-union membership) in profiles, stubs or nominations; the intake forms exclude these and free text is checked. | — | M | BOTH |
| REQ-068 | Individuals under 18 cannot hold a profile. | — | M | BOTH |
| REQ-069 | Access is role-based and least-privilege; bulk export of personal data is limited to the Administrator role and logged; Reviewers cannot export. *(Amended by D-040.)* | — | M | SITE |
| REQ-070 | **WITHDRAWN** (no payment data held by the site; offline payments are recorded in the register, REQ-179). ~~Only gateway reference IDs and amounts are stored for payments, never card numbers.~~ | — | Withdrawn | SITE |
| REQ-071 | A takedown/complaint process (defamation, privacy, IP) is published, tracked and has a response target. | 15.7 | M | SITE |
| REQ-072 | A data-protection impact assessment is completed and a data-protection contact designated, in parallel with the build. If Legal objects, bulk unpublish by filter (REQ-193) is used. | — | M | BOTH |

## 4. Integrations

| ID | Requirement | Source | Pri | Scope |
|---|---|---|---|---|
| REQ-073 | **WITHDRAWN** (no payment gateway; D-005). ~~A payment gateway supporting NGN cards and bank transfer is integrated via hosted checkout; webhooks are signature-verified and idempotent.~~ | 8 P1 | Withdrawn | SITE |
| REQ-074 | **WITHDRAWN** (superseded by the admin commercial register, REQ-179). ~~Payments made by bank transfer/invoice can be confirmed by Finance in the admin and recorded against a subscription.~~ | 9.3 | Withdrawn | SITE |
| REQ-075 | **AMENDED 2026-11-09 (D-049).** Transactional email is sent through the SMTP of a cPanel mail account with SPF, DKIM and DMARC configured; failures and bounces found by staff are recorded. A mail provider may replace it later. | — | M | SITE |
| REQ-076 | **WITHDRAWN** (receipts and invoices are issued offline by Finance). ~~Receipts and invoices (PDF, with VAT where applicable) are generated for each payment.~~ | 9.3 | Withdrawn | SITE |
| REQ-077 | The commercial register, leads and enquiries can be exported/imported (CSV) for CaromHQ by the Administrator (logged, REQ-069); a live integration is built only if CaromHQ exposes an API. | 15.5 | Q16 | SITE |
| REQ-078 | Privacy-friendly web analytics measure visits, journey completion and conversions. | 14.3 | M | SITE |
| REQ-079 | Search is provided by the database or a hosted search service sized for ≥ 10k profiles. | 5.2 | M | SITE |
| REQ-080 | **AMENDED 2026-11-09 (D-050).** Uploaded media is stored in the WordPress media library on the host (originals kept). Object storage and CDN offload are not used; a CDN may cache images later. *(Was: object storage behind a CDN.)* | 12.1 | M | SITE |
| REQ-081 | Public forms are protected by a bot challenge. | — | M | SITE |
| REQ-082 | Pages expose social share cards; social links are displayed as links only (no social-platform API use). | 10.5 | M | SITE |

## 5. Admin & ops

| ID | Requirement | Source | Pri | Scope |
|---|---|---|---|---|
| REQ-083 | An admin console (wp-admin) with exactly three staff roles, each with least privilege (REQ-235): Reviewer, Editor and Administrator. The anonymous Visitor has no account. There is no Finance, Sales/Support, Awards manager, Editor-in-Chief or Judge role. *(D-033 as amended: no AI service roles. Amended by D-044.)* | 13 | M | SITE |
| REQ-084 | A review queue shows each pending item with a field-level diff, sources and approve / edit / reject-with-reason actions. *(D-033: imported records are reviewed here; REQ-222.)* | 8 P2 | M | SITE |
| REQ-085 | Staff can create or edit a profile on behalf of a subscriber or as a complimentary listing; these are flagged and attributed. | 9.3 | M | SITE |
| REQ-086 | Admins can edit a customer record's status, extend or end a term, or mark it complimentary; each change requires a reason and is audited. Refunds and cancellations are handled offline by Finance and recorded as a note and status only. | — | M | SITE |
| REQ-087 | The commercial register exports to CSV with the counting-rule status of each row; export is Administrator-only and logged (REQ-069). | 9.3, 14 | M | SITE |
| REQ-088 | A weekly dashboard shows: paid subscriptions, revenue, average value, profiles submitted/published, journey completion rate, visitors, enquiries, nominations. Commercial figures are visible only to the Administrator (REQ-239). *(Amended by D-035, D-044.)* | 14.2–14.5 | M | SITE |
| REQ-089 | A support inbox tracks typed items (Enquiry, Correction, Removal, Report, Complaint) with owner, status and age; Removal items show a due-date countdown against the response target in REQ-158. *(Amended by D-040.)* | 8 P2 | M | SITE |
| REQ-090 | Any visitor can report a profile; reports enter an abuse queue visible to staff. | 15.7 | M | SITE |
| REQ-091 | Staff can unpublish or hide any profile or article immediately. | 15.7 | M | SITE |
| REQ-092 | Staff can bulk-import profiles from CSV with validation and a dry-run, tagged with source and importer. | 8 P3 | M | BOTH |
| REQ-093 | Prospect lists can be imported/exported (CSV) in a store separate from public data (see REQ-176). | 9.2 | S | SITE |
| REQ-094 | Email notifications fire for: enquiry received (to sales), profile received, approved, rejected with reason, nominee invitation, correction, removal or report received, and request resolved. Renewal and expiry reminders go to admins only. *(Amended by D-036.)* | 8 P2 | M | SITE |
| REQ-095 | An operations runbook documents deploy, rollback, backup restore and incident handling. | 15.5 | M | SITE |

## 6. Timeline-driven

| ID | Requirement | Source | Pri | Scope |
|---|---|---|---|---|
| REQ-096 | By **20 Nov 2026**: homepage, core landing pages and the agreed site structure are viewable (staging acceptable). | 8 P1 | M | SITE |
| REQ-097 | **WITHDRAWN** (no payment integration to test; D-005). ~~By **20 Nov 2026**: subscription packages are configured and payment + confirmation work end-to-end in the gateway's test mode.~~ | 8 P1 | Withdrawn | SITE |
| REQ-098 | By **20 Nov 2026**: individual, business and institutional profile templates and the directory structure exist. | 8 P1 | M | SITE |
| REQ-099 | **WITHDRAWN** (no live payments; REQ-177 and REQ-179 must be live instead). ~~By **21 Nov 2026**: live-mode payments and confirmation emails work, because direct outreach and "secure the first paid subscriptions" start that day.~~ | 8 P2 | Withdrawn | SITE |
| REQ-100 | By **21 Nov 2026** (ceiling): Terms, Privacy Policy and commercial terms are published before any customer data is collected. | 12.2 | M | SITE |
| REQ-101 | By **20 Dec 2026** (ceiling): first paying customers have their arrangements recorded and profiles reviewed and published, and the support process works. | 8 P2 | M | SITE |
| REQ-102 | By **20 Dec 2026**: weekly reporting (REQ-088) is live. | 14.6 | M | SITE |
| REQ-103 | By **31 Jan 2027** (ceiling): the website, enquiry journey and admin register operate in production. | 8 P3 | M | SITE |
| REQ-104 | By **31 Jan 2027** (ceiling): the directory holds reviewed profiles. No directory-size target number is stored or shown on the website or its admin. *(Amended by D-035.)* | 8 P3 | Q1 | BOTH |
| REQ-105 | By **31 Jan 2027** (ceiling, not target): the sprint target is met if either condition holds: ≥ 10 paid subscriptions (counted under REQ-022) or NGN 5m confirmed revenue (all streams, REQ-180). | 8 P3, 9 | M | SITE |
| REQ-106 | The Nominate page shows "Nominations open soon" with an email sign-up while the nominations flag is OFF (REQ-194). The flag is turned on only with the Venture CEO's explicit approval after the award rules and eligibility page are published. | 8 P2, C9 | M | SITE |

## 7. [implied] but unstated

| ID | Requirement | Source | Pri | Scope |
|---|---|---|---|---|
| REQ-107 | **WITHDRAWN** (D-034: front-end registration is closed (REQ-229).) ~~Users can register with email verification.~~ | — | Withdrawn | SITE |
| REQ-108 | Staff can log in to wp-admin, log out, reset passwords and see/revoke their own sessions; a reset never bypasses two-factor (REQ-047). There is no public login (REQ-229). *(Amended by D-034.)* | — | M | SITE |
| REQ-109 | Roles are enforced: the anonymous visitor (no account) and the three staff roles in REQ-235 (default capability bundles, REQ-244). There is no profile-owner, nominator, nominee or judge account. *(Amended by D-034.)* | — | M | SITE |
| REQ-110 | **WITHDRAWN** (D-034: no user accounts to deactivate. Staff leavers are deactivated under REQ-235.) ~~A user can deactivate or delete their account; the effect on their profile and arrangements is defined and shown before confirming.~~ | — | Withdrawn | SITE |
| REQ-111 | **WITHDRAWN** (deferred: one login per profile owner for now; D-030). ~~An organisation account supports multiple users (one owner, several editors).~~ | 6.2 | Withdrawn | SITE |
| REQ-112 | Money is stored as integer minor units with a currency code (default NGN). Revenue is recorded by stream (REQ-180). | — | M | SITE |
| REQ-113 | **WITHDRAWN** (no online payments to retry). ~~Failed or abandoned payments can be retried via a link; no profile is published without confirmed payment.~~ | 9.3 | Withdrawn | SITE |
| REQ-114 | **WITHDRAWN** (no webhooks to reconcile). ~~Gateway webhooks are reconciled against the register daily; duplicates and mismatches are flagged.~~ | — | Withdrawn | SITE |
| REQ-115 | Refunds and cancellations are handled offline by Finance; the platform stores status and notes only. | — | M | SITE |
| REQ-116 | Each arrangement stores start and end dates; admins see expiry reminders (REQ-189). When an arrangement ends, the Sponsored label it set is removed automatically; no profile or post is deleted. | — | M | SITE |
| REQ-117 | **WITHDRAWN** (no online plan changes; tier label and expiry are admin-edited, REQ-185). ~~Plan upgrades/downgrades follow a defined proration rule.~~ | — | Withdrawn | SITE |
| REQ-118 | **WITHDRAWN** (no promo codes at checkout; complimentary flag in REQ-179). ~~Promo codes and complimentary grants can be created by Admin.~~ | 9.3 | Withdrawn | SITE |
| REQ-119 | **WITHDRAWN** (offline invoicing is done by Finance outside the platform). ~~Corporate customers can be invoiced and pay by bank transfer.~~ | 6.2 | Withdrawn | SITE |
| REQ-120 | Terms, Privacy, Cookie policy, commercial terms and Awards rules are published. | 12.2, 13.2 | M | SITE |
| REQ-121 | 404/500 pages, robots.txt and sitemap.xml exist. | — | M | SITE |
| REQ-122 | Public forms are rate-limited per IP and per email address or link token. *(Amended by D-034.)* | — | M | SITE |
| REQ-123 | The system warns about likely duplicate profiles at submission and in review. | — | M | SITE |
| REQ-124 | **WITHDRAWN** (D-036: no claiming, so no two-party claim disputes.) ~~A process exists for two parties claiming the same profile (ownership dispute).~~ | — | Withdrawn | SITE |
| REQ-125 | Profile edits keep version history with author and timestamp. | 15.7 | M | SITE |
| REQ-126 | Submitted content is covered by a licence and accuracy warranty accepted at submission. | — | M | SITE |
| REQ-127 | Uploaded files are type-checked and scanned. | — | M | SITE |
| REQ-128 | **WITHDRAWN** (D-034: no owner area. Staff preview drafts in wp-admin (REQ-084).) ~~Owners can preview a draft exactly as it will be published.~~ | — | Withdrawn | SITE |
| REQ-129 | Slug changes create redirects from the old URL. | — | S | SITE |
| REQ-130 | A public "How [brand name] compiles profiles and what each label means" page exists (name from the theme config). | 3.4 | M | SITE |
| REQ-131 | The venture can export all its own data (profiles, register, content) in open formats. | 13.1 | S | SITE |

## 8. Data acquisition and population (Nigeria first)

### 8.1 Assessment of the four approaches

| | Approach | Verdict | Why |
|---|---|---|---|
| A | Organisations from sanctioned public sources | **Do in MVP** | Mostly company facts, low data-protection risk, many structured sources, and a legitimate starting point for sales. Risk: stale or wrong data, source licence terms. |
| B | Scraping for prospecting only | **Not a product feature** | A sales-research task. Product needs only CSV import/export and a hard wall between prospect data and public data (REQ-093, REQ-176). Use licensed tools or manual research; tool terms still need checking. |
| C | Public stubs of individuals | **Defer unless Legal signs off (Q2, Q5)** | Publishing personal data of people who did not ask to be listed is the highest legal and reputational risk (NDPA, defamation, harm if wrong). Safer variant: pre-built profile shown only to the person via private claim link; published only on consent. |
| D | AI-written bios from scraped pages | **Not in MVP (Q6)** | Hallucination risk is largest where harm is largest (individual bios). If allowed later: claim-to-source mapping and human review of every claim. |

### 8.2 Candidate sources (Nigeria) — to be confirmed

*These are candidates from general knowledge, not checked in this session. Each URL, licence, robots rule and access method must be verified and approved by a named person before use (REQ-136, REQ-137).*

| Entity type | Candidate source | Likely access | Data |
|---|---|---|---|
| Registered companies / NGOs | Corporate Affairs Commission (CAC) public search | Manual lookup; bulk/licensed data only by agreement | Name, RC number, status, type, date |
| Listed companies | Nigerian Exchange (NGX) issuer listings and annual reports | Web pages and PDFs; manual or permitted export | Name, sector, board and officers named in reports |
| Banks and payment firms | Central Bank of Nigeria licensee lists | PDF/web; manual or CSV transcription | Name, licence type |
| Capital market | SEC Nigeria registered operators | Web; manual | Name, category |
| Insurance / pensions / telecoms | NAICOM, PenCom, NCC licensee lists | Web/PDF; manual | Name, licence type |
| Universities | National Universities Commission accredited list | Web/PDF; manual | Name, type, location |
| Any organisation | Wikidata | **API / SPARQL (CC0)** | Name, founded, HQ, website, QID |
| Any organisation | Official website of the organisation | Manual, or crawl only if robots/terms allow | Description, leadership page, address |
| Companies (commercial) | A licensed company-data API (e.g. OpenCorporates or a Nigerian provider) | **API, paid/licensed** | Registry mirror |
| Individuals (public roles) | Board and officer lists in regulator filings or annual reports; official government bios; Wikidata | Manual extraction; API for Wikidata | Name, role, organisation |
| Individuals (other) | Wikipedia | Facts only; text is CC BY-SA, so copying prose imposes attribution/share-alike | Not recommended as bio text |
| **Excluded** | LinkedIn, Facebook, Instagram, X, any login-walled or ToS-forbidden site; third-party news article text; scraped photographs | — | Never ingested |

### 8.3 Requirements

| ID | Requirement | Source | Pri | Scope |
|---|---|---|---|---|
| REQ-132 | Organisation profiles can be created from approved sources and imported into WordPress with the review-state label (REQ-216). *(D-033: live on import before human review, labelled and noindex; REQ-237. D-036: no Unclaimed label.)* | brief | M | BOTH |
| REQ-133 | Individual stubs (approach C) are enabled in the MVP and published without the individual's consent under the rules in REQ-139 to REQ-176, with bulk unpublish by filter (REQ-193) and the suppression list. | brief | M | BOTH |
| REQ-134 | Bulk-import adapters exist for CSV/JSON and for the Wikidata SPARQL API; other sources are entered manually. | brief | M | BOTH |
| REQ-135 | Wikidata-sourced records store the QID and are re-synced on request. | brief | S | BOTH |
| REQ-136 | Each approved source is recorded in the acquisition pipeline's source registry: name, entity types, URL, access method (API / export / crawl / manual), licence and terms summary, robots.txt check date, approver, status. The website admin has a read-only Sources screen (REQ-243) and no add, edit, approve, reject or publish for sources; Reviewers never approve sources. *(Amended by D-038, D-042.)* | brief | M | BOTH |
| REQ-137 | Ingestion is permitted only from sources with status Approved; Approved requires a named person to have checked terms, licence and robots rules. Enforced in the acquisition pipeline, not in the website admin. *(D-038.)* | brief | M | ACQUISITION |
| REQ-138 | No automated collection from sites whose terms or robots rules forbid it, nor from login-walled sites or social platforms (including LinkedIn); any crawler identifies itself, rate-limits itself and never circumvents blocks. Enforced in the acquisition pipeline, not in the website admin. *(D-038.)* | brief | M | ACQUISITION |
| REQ-139 | Individual stubs hold only these fields: name, current title, current organisation, industry, public-record roles (board, office), published awards, official or organisational website. | brief | M | BOTH |
| REQ-140 | Never stored for individuals: home address, phone, personal email, family, date of birth or age, health, religion, politics, ethnicity, finances, allegations, or personal social handles. The schema offers no field for them. | brief | M | BOTH |
| REQ-141 | Free-text fields in any stub or draft are scanned for patterns (phones, emails, addresses, ages, sensitive keywords) and blocked from review until cleared. *(D-033: a record failing the scan is not exported; REQ-214.)* | brief | M | BOTH |
| REQ-142 | Every field on an acquired record stores: source URL, retrieval date, confidence (high / medium / low) and method (API / import / manual / AI). | brief | M | BOTH |
| REQ-143 | Fields with low confidence or no source cannot be published. | brief | M | BOTH |
| REQ-144 | Public stub pages show the source link for key facts and a "last reviewed" date. *(D-033: labelled organisations show the REQ-216 label instead of a date.)* | brief | M | SITE |
| REQ-145 | Each organisation and individual has one persistent ID; matching uses RC number, Wikidata QID, and normalised name + organisation + location. | brief | M | BOTH |
| REQ-146 | Probable duplicates are queued for human merge review; individuals are never auto-merged. | brief | M | BOTH |
| REQ-147 | A suppression list holds people and organisations who asked for removal; imports skip them. | brief | M | BOTH |
| REQ-148 | **AMENDED 2026-11-08 (D-033 as amended).** All acquired records pass basic verification (REQ-214) in the staging database (REQ-211) and are imported into WordPress (REQ-237). They are not published by the pipeline. A human reviews every record in the WordPress review queue (REQ-084, REQ-222) with sources visible. Organisations may be live, labelled and noindex, before that review; individuals and entrepreneurs are not public until it is done. The approving reviewer is not the importer or drafter. ~~**AMENDED by D-033.** All acquired records pass the publish gate (REQ-212) from the staging database (REQ-211) and publish under the tier rules (REQ-214). A human reviews every published record afterwards in the review queue (REQ-084, REQ-222) with sources visible. A record stays Published – review pending and noindex until a human approves it. The approving reviewer is not the importer or drafter. All acquired records go to the editorial review queue (REQ-084) with sources visible. 100% human review before publishing (no sampling); the approving reviewer is not the importer or drafter.~~ | brief | M | BOTH |
| REQ-149 | Review throughput, queue age and rejection reasons are tracked. | brief | M | SITE |
| REQ-150 | The profile trust labels are Verified and Featured; neither can be bought. Transitions are logged with actor and reason. There are no Unclaimed or Claimed states. *(Amended by D-036.)* | brief | M | SITE |
| REQ-151 | **WITHDRAWN** (D-036: no Unclaimed label; the review-state label is REQ-216.) ~~All acquired stubs carry a visible "Unclaimed" label explaining that WHOISWHO compiled it from public sources.~~ | brief | Withdrawn | SITE |
| REQ-152 | **WITHDRAWN** (D-036: no claiming.) ~~Claimed requires proof the claimant is the person or an authorised representative of the organisation. The claimant sends a request-to-claim form with evidence (REQ-230); staff verify it (REQ-231). A profile becomes Claimed only by a staff action.~~ | brief | Withdrawn | SITE |
| REQ-153 | Verified requires the checklist in REQ-025 and is independent of payment. | 3.4 | M | SITE |
| REQ-154 | Featured is an editorial decision recorded with reason; it cannot be bought. | 3.4 | Q11 | SITE |
| REQ-155 | Sponsored is a separate label that can sit on a profile of any status, does not alter the status, and is never styled like Verified. | 3.4, 6.3 | M | SITE |
| REQ-156 | Every public profile has "Suggest a correction", "Request removal" and "Report" actions. There is no claim action. *(Amended by D-036.)* | brief | M | SITE |
| REQ-157 | Correction and removal requests do not require an account or payment. | brief | M | SITE |
| REQ-158 | Response targets: acknowledge within 1 business day; hide the profile within 2 business days of a valid removal request from the subject; resolve corrections within 7 days. Breaches are flagged. | brief | M | SITE |
| REQ-159 | Where lawful and a business contact exists, the subject is told when a stub is created (as decided by Legal in Q5). | brief | Q5 | SITE |
| REQ-160 | Imported records are re-checked every 6 months, flagged stale at 12 months, and unpublished if not re-reviewed by 18 months; staff see them on the Stale records screen. *(Amended by D-036.)* | brief | S | BOTH |
| REQ-161 | Records synced from external sources flag source changes for review rather than overwriting. | brief | S | BOTH |
| REQ-162 | Stub profiles show no photograph unless it is owner-supplied or licensed. | brief | M | BOTH |
| REQ-163 | **Conflict with D-033 for AI-drafted organisations: see Q46.** Reviewers apply a neutral-content checklist: no allegations, litigation, rumours, opinion or personal life; only dated, sourced, professional facts. | brief | M | SITE |
| REQ-164 | A corrections log records every change made after publication on a stub, with the reason. | brief | M | SITE |
| REQ-165 | AI drafting happens in the acquisition pipeline under REQ-166 to REQ-170. It is not a website feature and has no feature flag on the website. *(Amended by D-037.)* | brief | M | ACQUISITION |
| REQ-166 | The model may only restate facts found in the fetched source text. Every claim is mapped to a stored source passage; any claim without a mapping is dropped automatically. | brief | M | ACQUISITION |
| REQ-167 | AI output uses a neutral, factual tone with no praise or superlatives, and makes no claims about legal trouble, health, family, finances or political views. | brief | M | ACQUISITION |
| REQ-168 | **Conflict with D-033 for AI-drafted organisations: see Q46.** A human reviewer approves every AI-drafted profile and sees each claim next to its source. Profiles record internally that they were AI-assisted, plus model, prompt version and source hash. | brief | M | BOTH |
| REQ-169 | The AI provider's API terms exclude training on our data; Legal confirms the data transfer; the provider is in the processor register (REQ-065). | brief | M | ACQUISITION |
| REQ-170 | **Must now describe organisations going live before review: see Q35.** The public disclosure page states how AI-assisted profiles are produced and reviewed (wording as Legal decides). | brief | M | SITE |
| REQ-171 | A dashboard reports profiles by entity type, source, status and age. | brief | S | SITE |
| REQ-172 | A subject may add a short right-of-reply note to a profile or editorial item, subject to review. | brief | S | SITE |
| REQ-173 | **WITHDRAWN** (D-036: no claimed owners. Subjects ask through the Support inbox (REQ-236).) ~~Claimed profile owners can ask, through the correction or contact form, which sources the platform used about them; staff send the list (REQ-236).~~ | brief | Withdrawn | SITE |
| REQ-174 | Disputes (correction, removal) are handled by whoever holds the handle-disputes capability (the Editor by default; REQ-244); an unresolved dispute goes to the Administrator. *(Amended by D-036, D-044, D-046.)* | brief | M | SITE |
| REQ-175 | Complimentary profiles created for a customer require that customer's recorded consent before publication, given on a single-use signed-link page (REQ-233) and stored on the arrangement. (Stubs follow REQ-133 instead.) *(Amended by D-034.)* | 9.3 | M | SITE |
| REQ-176 | Prospect data (approach B) lives in a separate store, is never public, and cannot become a profile except by entering the REQ-148 review path with an approved source. | brief | M | BOTH |

## 9. Non-software work (tracked, not built)

| # | Work | Spec | Owner (per spec) |
|---|---|---|---|
| N1 | Brand positioning, identity, messaging and tone guidelines | 2, 11, 12, P1 | Venture CEO + Brand & Creative |
| N2 | ~~Pricing and package definition~~ **Withdrawn**: no packages; arrangements are quoted per customer and recorded in the register | 6.1, P1 | — |
| N3 | Sales: prospect research (approach B), 500→300→150→100→10 funnel, outreach scripts, proposals, introductions | 9, 10, P1–P3 | Venture CEO / sales |
| N4 | Prospecting tools: licensed lead data or manual research; verify tool terms of service | brief | Sales |
| N5 | Sales presentation, proposal documents | P1 | Sales |
| N6 | Launch communications, social channels, content calendar, email marketing | 10.5, P1–P2 | Marketing |
| N7 | Editorial content production and media relations | 5.3, 10.3, P2 | Editorial |
| N8 | Final award categories, eligibility criteria, judging panel, governance and conflict-of-interest policy | 5.4, 7.3–7.4 | Venture CEO |
| N9 | Legal: subscription terms, privacy and data protection, award rules, sponsorship agreements, NDPA basis for publishing personal data | 13.2 | Legal |
| N10 | Sponsorship proposals and sponsor prospecting | 6.4, P3 | Venture CEO |
| N11 | Event concept, venue, budget, date, ticketing/tables, execution schedule | 5.5, 6.5, 7.5, P3 | Events |
| N12 | Partnerships (associations, chambers, alumni, media) | 10.4 | Venture CEO |
| N13 | ~~Payment-gateway merchant account~~ **Withdrawn** (no payment processing on the site) | P1 | — |
| N14 | CaromHQ record-keeping discipline | 15.5 | All |
| N15 | Reviewer recruitment and editorial guidelines for the review queue | brief | Editorial |
| N16 | Data-acquisition source approval (terms/robots/licence checks) | brief | Editorial + Legal |
| N17 | Next-quarter roadmap, Intelligence & Insights (5.6), expansion beyond Nigeria | 5.6, 18 | Venture CEO |
| N18 | External accounts and DNS: domain registrar, DNS, hosting, email, monitoring; secrets held in the host's secret store | 13.2 | You / Incspired IT |
| N19 | Brand asset delivery and approval | 12, P1 | Brand & Creative + Venture CEO |
| N20 | Offline payment collection, invoicing, receipts, refunds and renewals, done by Finance outside the platform; results recorded in the commercial register | Q10, D-005 | Finance |
| N21 | After any removal or bulk unpublish, request search-engine removal/refresh (e.g. Search Console removals) for already indexed URLs; the platform returns 410 and noindex meanwhile | D-029 | Editorial / admin |

## 10. Added after Gate 1 (scope change and decisions)

| ID | Requirement | Source | Pri | Scope |
|---|---|---|---|---|
| REQ-177 | The "Subscribe / Get Featured" page is an enquiry form (name, organisation, interest, contact) that creates a lead, plus informational content about being listed, sponsored posts and profiles, media/editorial work and awards sponsorship. No packages, tiers, cart or checkout. The form is spam-protected and rate-limited. | 12.2, D-005 | M | SITE |
| REQ-178 | Editors can manage leads: owner, status, notes, conversion to a customer and arrangement, CSV export. *(Amended by D-044.)* | D-005 | M | SITE |
| REQ-179 | Admins can create and edit arrangements in the commercial register. Fields: type (sponsored post, sponsored profile, media/editorial package, awards sponsorship, other), customer (with segment), amount, currency, payment method, payment reference, date received, confirmed-by, status (proposed, awaiting payment, confirmed, ended, cancelled), start date, end date, complimentary flag, notes. | 9.3, D-006, D-028 | M | SITE |
| REQ-180 | Revenue is reported by arrangement type and by revenue stream (subscription, media/editorial, sponsorship). Each arrangement type has an admin-editable default stream. | 6, C8 | M | SITE |
| REQ-181 | The admin dashboard shows both sprint numbers (count of Confirmed paid arrangements under REQ-022, with a breakdown by type; confirmed revenue total) and revenue per stream, to the Administrator only (REQ-239). Thresholds (10 and NGN 5m) are configuration; no single gate is hard-coded. *(Amended by D-035, D-044.)* | 8, 9, C8 | M | SITE |
| REQ-182 | Admins can mark any post or profile Sponsored with a sponsor name; the page, listing card and share preview show a visible disclosure label. A sponsored-post or sponsored-profile arrangement can be linked to the item and applies the label between its start and end dates; admins may also set it manually. | 6.3, D-006 | M | SITE |
| REQ-183 | Admins can set Featured (editorial) with a recorded reason, and Verified via the checklist of REQ-025. Neither can be set from an arrangement or payment field. | 3.4 | M | SITE |
| REQ-184 | Changing commercial data (arrangement, payment, sponsorship) never changes Verified or Featured, and the reverse; automated tests cover this. | 3.4 | M | SITE |
| REQ-185 | **WITHDRAWN** (no tiers, plans or entitlements; commercial relationships are arrangements, REQ-179; D-028). ~~Tier label and expiry are admin-editable per profile. Plans, tiers and entitlements are stored as configuration, not code.~~ | 6.1, Q7/Q8 pending | Withdrawn | SITE |
| REQ-186 | Sponsors are records (name, logo, link) that admins can attach to awards, categories and pages. | 6.4 | M | SITE |
| REQ-187 | Admins can keep internal notes on profiles, customers, leads and nominations; notes are never public and never visible to judges. | D-006 | M | SITE |
| REQ-188 | Every change to arrangements, Sponsored/Featured/Verified labels, sponsors and notes is written to the audit log (extends REQ-051) with actor, time, old and new values. | 15.7 | M | SITE |
| REQ-189 | Admins see reminders for upcoming expiries; the system takes no automatic action against customers. | Q10 | M | SITE |
| REQ-190 | **AMENDED 2026-11-09 (D-052).** All brand values (brand name, legal trading name, edition name, colours, fonts, logo, spacing) live in one central theme config; no brand values are hard-coded elsewhere, and a check fails the build if they are. | C5 | M | SITE |
| REQ-191 | No directory-size target number is stored or shown on the website or its admin. The demo/staging site is public and indexable by search engines; there is no private preview and no login protection. *(Amended by D-035: the earlier target of about 100 organisation and about 100 individual profiles is dropped.)* | Q1 | M | BOTH |
| REQ-192 | Sourcing of further organisations and individuals continues in parallel through the approved pipeline. | Q1 | M | ACQUISITION |
| REQ-193 | Bulk unpublish by filter replaces the global kill switch (D-043). An Administrator or Reviewer can unpublish every profile matching a filter (source, type, import batch and state, in any combination) at once, with a mandatory reason and a confirmation that shows the count. Each profile becomes 410 with noindex (REQ-209); the action is reversible per batch and audited with one row per profile and one for the batch. The suppression list (REQ-147, 207) and the removal flow (REQ-158) remain the other safety controls. *(D-045: Administrator and Reviewer only, by default; the Administrator can grant the capability to others (REQ-244). D-033: REQ-227 is folded in. Amended by D-036, D-037, D-043.)* | Q2 | M | SITE |
| REQ-194 | The only feature flag is Nominations (the public nomination form on or off). It defaults OFF; switching it on requires the Administrator role, records the CEO's approval reference, and is audited. Judging is offline (REQ-234); shortlist, winner and announcement pages publish through the normal publish step and have no flag. Bulk unpublish by filter (REQ-193) is not a flag. *(Amended by D-037, D-043.)* | C9, Q12 | M | SITE |
| REQ-195 | While nominations are off, the Nominate page shows "Nominations open soon" with an email sign-up (consent recorded, unsubscribe supported). | C9 | M | SITE |
| REQ-196 | Anyone may nominate. The nominator's email is verified by a single-use signed link (REQ-233); submissions are rate-limited with duplicate detection; there is no public voting. *(Amended by D-034.)* | Q15 | M (flag) | SITE |
| REQ-197 | Nominations are private until eligibility screening is complete; after screening the nominee is emailed a single-use signed link (REQ-233) to accept, decline or correct their details, with no account. *(Amended by D-034.)* | Q15 | M (flag) | SITE |
| REQ-198 | Public nominee pages appear only at the shortlist stage, built from the reviewed profile and sourced facts. A nominee who declines is removed and added to the suppression list (REQ-147). | Q15 | M | SITE |
| REQ-199 | Payment, subscription or sponsorship never creates a nomination or affects screening or shortlisting; there is no data path from commercial records into the awards workflow. | 5.4 | M | SITE |
| REQ-200 | Admins configure categories, eligibility criteria, scoring criteria and weights, the judging panel per category and shortlist size; none are hard-coded. Judges are names on a panel, not users. *(Amended by D-034.)* | Q12 | M | SITE |
| REQ-201 | **WITHDRAWN** (D-034: no judge accounts. Replaced by REQ-234 and REQ-037.) ~~Judge accounts see only assigned nominations and never payment, subscription or sponsorship data.~~ | Q12 | Withdrawn | SITE |
| REQ-202 | Eligibility screening, shortlisting and final approval are separate recorded steps with named approvers. | Q12 | M | SITE |
| REQ-203 | Images under open licences (e.g. Wikimedia Commons) may be used when the licence and attribution are stored with the image and shown on the page; no other third-party photos. | Q22 | M | BOTH |
| REQ-204 | The domain is wiwi.africa, including the email sending domain with SPF, DKIM and DMARC. | C1 | M | SITE |
| REQ-205 | Customers, leads and enquiries have stable external IDs and change timestamps so a CaromHQ integration can be added later; CSV import/export works meanwhile (REQ-077). | C12 | M | SITE |
| REQ-206 | The review queue shows counts by state and the oldest pending item. It shows no review-capacity figure until the pilot has measured one; any figure is labelled an estimate. *(D-033: the backlog is the WordPress review queue; REQ-222. Amended by D-035.)* | Q4 | M | SITE |
| REQ-207 | The suppression list is permanent, cannot be bypassed by any import, and covers removed individuals, declined nominees and removed organisations. | Q2, Q15 | M | BOTH |

## 11. Added after Q1/Q7 corrections

| ID | Requirement | Source | Pri | Scope |
|---|---|---|---|---|
| REQ-208 | **AMENDED 2026-11-08 (D-033 as amended).** A profile is publicly visible only in state Published – review pending (organisations, on import) or Published – reviewed (REQ-210). Individuals and entrepreneurs are public only after a human reviewer approves them. Imported – not public, rejected and unpublished profiles are visible to admin roles only. Customer-submitted profiles and owner changes are public only after a human approves them (REQ-014, 015). This holds across pages, search, sitemaps, feeds, APIs, previews and caches, and automated tests cover every state on every surface. ~~**AMENDED by D-033.** A profile is publicly visible only in state Published – review pending or Published – reviewed (REQ-210). Staged, held, rejected, taken-down and unpublished profiles are visible to admin roles only. Customer-submitted profiles and owner edits are public only after a human approves them (REQ-014, 015). This holds across pages, search, sitemaps, feeds, APIs, previews and caches, and automated tests cover every state on every surface. A profile is publicly visible only after a human reviewer approves it. Drafts, unreviewed, rejected and AI-drafted-pending profiles are visible to admin roles only. This holds across pages, search, sitemaps, feeds, APIs, previews and caches, and automated tests cover each.~~ | Q1 | M | SITE |
| REQ-209 | Unpublishing is immediate and works on public, indexed pages: removal, suppression, nominee decline and bulk unpublish make the URL return 410 Gone with a noindex header, drop it from the sitemap and internal search, and purge it from the CDN/cache. Restoring after a reversible unpublish returns 200. | Q1, Q2 | M | SITE |

## 12. Added for publish first (D-033)

These apply to acquired records (REQ-132, REQ-133) under D-033 as amended. Customer submissions, owner changes, complimentary listings and nominee pages stay approval-first (REQ-014, 015, 175, 198). They are the WordPress build's input (D-031). Withdrawn rows keep their numbers.

| ID | Requirement | Source | Pri | Scope |
|---|---|---|---|---|
| REQ-210 | **AMENDED 2026-11-08 (D-033 as amended).** Every profile has exactly one publication state, stored separately from its trust status (REQ-150): Imported – not public, Published – review pending, Published – reviewed, Unpublished, Rejected, Removed and suppressed. Only the two Published states are public. Only the transitions in the table below are allowed; any other attempt is refused and logged. ~~Every profile has exactly one publication state, stored separately from its trust status (REQ-150): Staged, Awaiting quick look, Held amber, Held red, Published – review pending, Published – reviewed, AI takedown, Unpublished, Rejected, Removed and suppressed. Only the two Published states are public. Only the transitions in the table below are allowed; any other attempt is refused and logged.~~ | D-033 | M | SITE |
| REQ-211 | **AMENDED 2026-11-08 (D-033 as amended).** Pipeline output is held in a private staging database outside WordPress that no public page, feed, API or search reads. Each record there has a content hash and a verification report. The pipeline holds no credentials that can publish. Personal data in it follows the same rules as live data (REQ-063, REQ-140). The run saves its progress and can resume. ~~Pipeline output is held in a staging database that no public page, feed, API or search reads. Each candidate version there has a content hash. Personal data in it follows the same rules as live data (REQ-063, REQ-140). See Q53.~~ | D-033 | M | ACQUISITION |
| REQ-212 | **AMENDED 2026-11-08 (D-033 as amended).** Import checks, in code: the record has a passing verification report (REQ-214); its recomputed content hash matches (REQ-221); suppression check clear (REQ-207); every field sourced and not low confidence (REQ-143); under-18 guard (REQ-068). Any failure refuses that record and logs the reason. A named staff member starts each import. ~~Code, not the model, decides publication. In one transaction the publish gate requires: source Approved (REQ-137); every field sourced and not low confidence (REQ-143); sensitive scan clear (REQ-141); quote verification passed (REQ-166); AI audit passed (REQ-213); inclusion criterion evidence present; suppression check clear (REQ-207); under-18 guard (REQ-068); no pause in force (REQ-224); under the cap (REQ-225); content hash equal to the version checked (REQ-221). Any failure blocks publication and logs the reason.~~ | D-033 | M | BOTH |
| REQ-213 | **WITHDRAWN** (second independent AI audit removed from acquisition by D-033 as amended). ~~Every record gets an AI audit before publication, including records whose text is not AI-drafted. The audit is a separate model run that sees only the stored source passages and the draft, and returns a verdict per claim (supported, identity consistent, neutral, no prohibited topic). It can only lower a record's colour, never raise it. Model, prompt version and verdict are stored.~~ | D-033 | Withdrawn | ACQUISITION |
| REQ-214 | **AMENDED 2026-11-08 (D-033 as amended).** Basic verification is exactly seven checks: the source is on the approved list; the source URL returned success; every AI-drafted claim has an exact quote found in the stored source text (unsupported claims are dropped); only allowlisted fields are kept; the sensitive-term scan passes; the suppression check passes; probable duplicates are flagged. A record failing an approved-source, URL, scan or suppression check is not exported. A flagged duplicate is exported and is not public on import, even if it is an organisation (REQ-146). Organisation records keep no named person in any field that can be public before review. Each record carries a short verification report: run ID, the results, sources with retrieval dates, claims kept and dropped, flags, model and prompt version, content hash. ~~The gate gives each record a colour. Green: all checks and the audit pass. Amber (waits for full human review, then goes live as reviewed): medium-confidence field, audit unsure, probable duplicate, sources disagree, an individual's current role not stated on an official page fetched in the last 30 days, the first 10 records from a new source, a current public office-holder, a source that cannot be monitored automatically. Red (not published until a human clears it): suppressed match, sensitive or prohibited content, an unsupported claim, low-confidence or unsourced field, no criterion evidence, unapproved source, possible minor. Green structured-source organisation records with no named person publish automatically. Green individuals and entrepreneurs wait for a quick look (REQ-215). AI-drafted organisation records follow Q46; individuals may change under Q47.~~ | D-033 | M | ACQUISITION |
| REQ-215 | **WITHDRAWN** (quick look removed; individuals and entrepreneurs are not public until a reviewer approves). ~~The quick-look screen shows name, organisation, role, the evidence page and the inclusion criterion, with a timer. The person checks four things: right person, criterion met, role still current on the evidence page, nothing prohibited at a glance. Decisions: go live, hold for full review, reject. A quick look is not a review: the profile stays Published – review pending. The person is not the importer.~~ | D-033 | Withdrawn | SITE |
| REQ-216 | A Published – review pending profile (organisations on import, D-033 as amended) shows, on its page, on any card and in its share preview, the label "Compiled automatically from public sources. Human review pending." with a correct/remove link and a link to the how-we-compile page (REQ-130). After human approval it shows "Reviewed on <date>", which satisfies REQ-144. The label is not a trust status (REQ-024), and is never styled like Verified. The wording is one configuration value so Legal can change it. | D-033 | M | SITE |
| REQ-217 | A Published – review pending profile returns HTTP 200 with an `X-Robots-Tag: noindex` header and a robots noindex meta tag. It has no sitemap entry, feed entry or schema.org markup, including anything generated by a plugin. robots.txt must not block these URLs, because a crawler has to read the page to see the rule. On human approval the noindex is removed, markup and sitemap entry are added, and the cache is purged in the same flow. Automated tests cover every state on every surface (extends REQ-208). | D-033 | M | SITE |
| REQ-218 | Indexable listing pages (home, discover, industry) show reviewed profiles only, so a pending profile's name and title cannot be indexed through a list. Pending profiles are reachable by direct URL, on-site search and a noindex "include profiles pending review" view, always with the label. See Q54. | D-033 | M | SITE |
| REQ-219 | **WITHDRAWN** (no AI service roles; the pipeline never publishes and holds no publishing credentials. If REST is chosen, see REQ-237). ~~Two non-human WordPress roles have separate credentials. AI Publisher may only move a record to Published – review pending. AI Monitor may only move a published profile to AI takedown and restore it under REQ-221. Neither may edit any field, approve a review, or change trust status, labels, flags, thresholds, the cap, suppression or users. Credentials are scoped, revocable service tokens held by code, never passed to the model, rotated and restricted by source address. Staff MFA (REQ-047) does not apply to them.~~ | D-033 | Withdrawn | BOTH |
| REQ-220 | **WITHDRAWN** (AI takedown removed from acquisition; future item REQ-238). ~~The AI Monitor may take down a profile with a reason code from a fixed list. Takedown is reversible and behaves as REQ-209 (410, noindex, sitemap and search removal, purge). The public page never shows or implies the reason. Every takedown enters a queue for human review, due in 1 business day for S1-class codes and 2 for others, with the outcome uphold, overturn or correct. By default the monitor takes down pending profiles for any code, and reviewed, claimed, verified or sponsored profiles for S1-class codes only; other findings on those are flagged for a human (Q52).~~ | D-033 | Withdrawn | SITE |
| REQ-221 | **AMENDED 2026-11-08 (D-033 as amended).** What is imported must be what was drafted, confirmed by content hash: the pipeline writes the hash, and WordPress recomputes it at import and refuses a mismatch. Later human edits are recorded in version history (REQ-125). Re-importing never overwrites a reviewed field (REQ-161). ~~What is live must be the exact version checked, confirmed by content hash. Republishing requires a hash-checked version and a full pass of the publish gate. The AI may restore only after a technical reason code (for example a dead source that has recovered); every other code needs a human. The AI never edits facts. An automatically redrafted version never replaces a reviewed profile without human approval (REQ-161). Items reviewed in review packs import only if their hash matches the approved hash.~~ | D-033 | M | BOTH |
| REQ-222 | **AMENDED 2026-11-08 (D-033 as amended).** Every imported record is reviewed by a human in the WordPress review queue, with each claim beside its source and the verification report (REQ-084, 163, 168). Approve sets Published – reviewed with the date, makes an individual or entrepreneur public, lifts noindex and adds the sitemap entry. Reject makes the record not public (410 if it was live). Default queue order: pilot, then organisations, then individuals (Q31); claims and corrections rise to the top. The reviewer records any error severity (S1, S2, S3) and is not the importer. A random 5% of reviewed profiles is re-checked weekly. ~~Every published record is reviewed by a human in the review queue, with each claim beside its source (REQ-084, 163, 168). Approve sets Published – reviewed with the date; reject makes it 410. Queue order: S1 flags, measurement sample, profiles with a claim or correction, then oldest. The sample is the first 100 records of each tier, then a random 20% of each lot (at least 20 records), due within 3 business days, with the AI audit hidden from the reviewer. The reviewer records any error severity (S1, S2, S3) and is not the importer.~~ | D-033 | M | SITE |
| REQ-223 | **WITHDRAWN** (continuous AI monitoring removed from acquisition; future item REQ-238). ~~A monitor runs continuously on published profiles: source link health, source content change, prohibited-topic scan, conflicts between sources, plus suppression re-check and a heartbeat. It obeys REQ-138 for every fetch, and fetched text is treated as data, never instructions. Findings flag or take down; they never overwrite a field (REQ-161). Cadence is a setting, by default daily or weekly for pending and weekly or monthly for reviewed (plan section 6.8).~~ | D-033 | Withdrawn | SITE |
| REQ-224 | **WITHDRAWN** (automatic pause removed from acquisition pending Q66; people apply the S1 and S2 rules at each import (plan 6.1)). ~~New publishing pauses automatically when a threshold in plan section 6.1 is passed (defaults: any S1; individuals 3 or more S2 in the last 100 reviewed; organisations 4 or more; 2 or more in the first 30 of any tier; sample overdue; oldest pending older than 21 days; monitor heartbeat missed for 48 hours). A pause can be per tier, per source or global, blocks REQ-212, and is lifted only by a named human with a reason. Thresholds are settings. A pause never unpublishes anything by itself except the S1 profile.~~ | D-033 | Withdrawn | SITE |
| REQ-225 | **WITHDRAWN** (cap removed pending Q66; a named person starts each import, and Q65 covers the first batch). ~~Pending profiles are capped. Default: the lower of 400 or two weeks of measured review capacity, with individuals and entrepreneurs at no more than 150 (or the same proportion of a lower cap). Records beyond the cap stay in Staged. Each ramp stage (50, 150, 300, then the cap) is opened by a named human after the previous stage's sample passes.~~ | D-033 | Withdrawn | SITE |
| REQ-226 | **AMENDED 2026-11-08 (D-033 as amended).** A Published – review pending profile cannot be Verified, Featured or Sponsored, cannot be linked to an arrangement, and cannot appear on an awards or nominee page. A claim or correction moves it to the top of the review queue. Removal requests follow REQ-158. ~~A pending profile cannot be Verified, Featured or Sponsored, cannot be linked to an arrangement, and cannot appear on an awards or nominee page. A claim or correction moves it to the top of the review queue. A removal request on a pending profile hides it at once (reversible, code SUBJECT_REQUEST), with rate limiting against abuse; REQ-158 deadlines still apply to acknowledging and deciding it.~~ | D-033 | M | SITE |
| REQ-227 | **WITHDRAWN** (D-043: folded into bulk unpublish by filter, REQ-193; filter by state and import batch.) ~~A restricted bulk action unpublishes all Published – review pending profiles, or one import batch of them, with the same effect and audit as the kill switch.~~ | D-033 | Withdrawn | SITE |
| REQ-228 | **AMENDED 2026-11-08 (D-033 as amended).** Every import is written to the audit log with the person, batch, file hash, record counts and states set (extends REQ-051, 188). The dashboard shows imported, public, reviewed and awaiting review, by tier, with the oldest waiting, error rates and reasons (extends REQ-149, 171). ~~Every AI publish, takedown, restore, audit verdict, gate result, pause and cap event is written to the audit log with the service role, reason code and content hash (extends REQ-051, 188). The dashboard shows live, reviewed and pending counts by tier, oldest pending, error rates against thresholds, pause state, takedowns and their outcomes, and monitor heartbeat (extends REQ-149, 171).~~ | D-033 | M | SITE |

### 12.1 Publication states

| State | Public? | Response | Search engines | Public label |
|---|---|---|---|---|
| Imported – not public, Rejected | No | 404 | Not indexable | None |
| Published – review pending (organisations on import) | Yes | 200 | noindex; no sitemap or schema.org | "Compiled automatically from public sources. Human review pending." with correct/remove link |
| Published – reviewed | Yes | 200 | Indexable; sitemap and schema.org | "Reviewed on <date>" |
| Unpublished | No | 410 (restore gives 200) | noindex | None; no reason shown |
| Removed and suppressed | No | 410, permanent | noindex | None |

### 12.2 Who may move a profile between states

| From | To | Actor | Condition |
|---|---|---|---|
| (import) | Published – review pending | Named staff member, by running the import | Organisation with no duplicate flag; REQ-212; safeguards in plan 5.4 pass |
| (import) | Imported – not public | Named staff member, by running the import | Individual, entrepreneur, flagged duplicate, or the import-all-as-not-public setting |
| Imported – not public | Published – reviewed, Rejected | Reviewer | REQ-222 |
| Published – review pending | Published – reviewed, Rejected (410) | Reviewer | REQ-222 |
| Any public state | Unpublished | Staff, bulk unpublish by filter, REQ-193 | REQ-091, 193, 209 |
| Unpublished | Previous state | Staff | Audited |
| Any state | Removed and suppressed | Staff | REQ-158, 207 |

## 13. No public accounts (D-034)

Claiming, profile changes, nominations and judging all work without any visitor, owner, nominator, nominee or judge account. Staff do the work in wp-admin. These are the WordPress build's input (D-031). Defaults marked Q56 to Q62 await your rulings.

| # | Requirement | Spec | Priority | Scope |
|---|---|---|---|---|
| REQ-229 | The public website has no registration, login, password reset or account area for visitors, profile owners, nominators, nominees or judges. WordPress front-end registration is closed (no self sign-up, no default role, no user-facing login link). The only WordPress users are staff (REQ-235). | D-034 | M | SITE |
| REQ-230 | **WITHDRAWN** (D-036: no request-to-claim form.) ~~"Claim this profile" is a request-to-claim form with no account. Fields: claimant name, relationship (the person, or an authorised representative of the organisation), email, phone, evidence upload (type and size limits), and a statement. The email is verified by a single-use signed link (REQ-233) before the request enters the claim queue. Rate-limited and spam-protected. The claimant gets a reference number and an email when the request is resolved.~~ | D-034, D-022, REQ-152 | Withdrawn | SITE |
| REQ-231 | **WITHDRAWN** (D-036: no claim verification screen.) ~~A staff claim-verification screen shows each request beside the profile, the evidence, the method (domain email or document, D-022) and any rival claim. Staff approve (profile becomes Claimed, REQ-150), reject with a reason, or escalate a dispute to the Editor-in-Chief (REQ-124, 174). Only this action sets Claimed. Every decision is audited with actor and reason.~~ | D-034, D-022 | Withdrawn | SITE |
| REQ-232 | Correct, remove and report are public forms with no account. A request records the requester's name, relationship, email (verified by a single-use link, REQ-233), the fields to change, the new values and evidence. Each submission lands in the Support inbox (REQ-089) as a typed item (Correction, Removal, Report) with a status and, for removals, a due-date countdown (REQ-158). Staff act by editing or unpublishing the profile directly in wp-admin; every edit and unpublish is written to the audit log (REQ-188) and version history (REQ-125) automatically. A valid removal also adds the person or organisation to the suppression list (REQ-147, 207). There is no separate corrections screen. *(Amended by D-040.)* | D-034, REQ-015, 156, 157 | M | SITE |
| REQ-233 | Single-use signed email links carry the account-free flows: nominator verification (REQ-196), nominee accept, decline or correct (REQ-197), customer intake (REQ-013), consent for complimentary profiles (REQ-175) and email verification of correction, removal and report requests (REQ-232). Each link is bound to one purpose and one record, expires (default 14 days; Q58), works once, is stored hashed, shows an expired-or-used page afterwards, carries no personal data in the URL, is rate-limited, and can be revoked or reissued by staff. Every use is audited. *(Amended by D-036.)* | D-034 | M | SITE |
| REQ-234 | Judging is offline. An Editor (or the Administrator) records, in wp-admin, each judge's score per criterion for each nomination, using the weights in REQ-200, plus conflicts (REQ-036), the shortlist and the results. The totals are computed. Every entry is audited with who entered it and when. The screens show no commercial data (REQ-037). Shortlist, winner and announcement pages publish through the normal publish step (REQ-194). *(Amended by D-037, D-044.)* | D-034, D-019 | M | SITE |
| REQ-235 | Three staff roles plus the anonymous Visitor, each with least privilege. **Reviewer:** review queue, duplicates, stale records, profiles (view, filter, assign existing taxonomy terms), bulk unpublish by filter, read-only Sources and read-only suppression list. **Editor:** everything a Reviewer has, plus edit, publish and unpublish (single profiles), stories, the Sponsored label, taxonomy terms, nominations and awards handling with scores entered by staff, the Support inbox and leads. **Administrator:** everything, including bulk unpublish by filter, plus users and roles, settings, imports, the commercial register (the Administrator alone confirms payments), revenue and sprint tiles, exports (logged), the audit log, suppression-list changes and the Nominations flag. These are default capability bundles; the Administrator can grant or revoke individual capabilities per user (REQ-244). A role-by-capability matrix is documented and tested; only the Administrator installs plugins or changes roles; two-factor is required (REQ-047). Leavers are deactivated the same day and the change is audited. *(Amended by D-036, D-039, D-040, D-044.)* | D-034 | M | SITE |
| REQ-236 | Information a subject asks for (profile status, view count, sources used, enquiries) is sent by staff through the Support inbox, from the same data staff already see. Each such request is logged. *(Amended by D-036.)* | D-034, REQ-019, 173 | S | SITE |

## 14. Import path and future items (D-033 as amended)

| ID | Requirement | Source | Pri | Scope |
|---|---|---|---|---|
| REQ-237 | **First acquisition task.** A basic import path loads staging exports into the WordPress profile post type, by file upload (JSON Lines, run by an Administrator in wp-admin or by CLI); REST is not used because it would need a service account, which is a fourth role (Q67, Q77: defaults accepted, D-047). A named staff member can dry-run a file and see a diff, then import it. Records are created or updated by stable ID, so importing the same file twice changes nothing. Sources, per-field provenance, claims, verification report, batch ID and content hash are stored with each record. Import applies REQ-212, sets the state by record type (REQ-210), has a setting to import everything as not public, never overwrites a reviewed field (REQ-161), can undo a whole batch, and handles 1,000 records within the host's limits. Format: `docs/contracts/import-format.md`. Priority input for the architecture revision. | D-033 | M | BOTH |
| REQ-238 | **Future item, not in acquisition scope.** Continuous AI monitoring of published profiles and AI takedown, as a separate WordPress feature. Not designed or built now. REQ-213, 219, 220, 223 and 224 hold earlier thinking as a starting point. | D-033 | L | SITE |
| REQ-239 | The staff dashboard shows plain counts by state (imported, pending human review, reviewed, published) and no directory-size target, progress bar toward a profile count, or review-capacity figure; any capacity figure is labelled an estimate until the pilot measures it. The dashboard is role-based: a Reviewer sees only review and directory tiles; an Editor also sees support and nominations counts; the sprint target, revenue per stream and arrangements are visible only to the Administrator. *(Amended by D-044.)* | D-035 | M | SITE |
| REQ-240 | Each staff role sees only the menu items it needs and is refused (403) on the rest; the role-to-menu list is documented on the Roles and permissions screen and tested. The Reviewer menu is exactly: Dashboard (review tiles), Review queue, Duplicates, Stale records, Profiles, Sources (read-only), Suppression list (read-only). Menus and routes follow each user's effective capabilities (REQ-244). *(Amended by D-044, D-046.)* | D-041, REQ-235 | M | SITE |
| REQ-241 | In the review queue a Reviewer sees, read-only, each claim's source (URL and retrieval date) next to the claim. Each profile stores per-field source and retrieval date (REQ-139). Reviewers never approve or edit sources; approved sources are managed in the acquisition pipeline (REQ-136 to 138). | D-038 | M | SITE |
| REQ-242 | Every profile carries a source field and an import batch field (origin: import or customer). The staff Profiles list can be filtered and grouped by source, import batch, type and state, and a bulk action can act on the filtered set (REQ-193). Each field's own source (URL and retrieval date) is shown on the profile and in the review queue (REQ-241). | D-042 | M | SITE |
| REQ-243 | A read-only Sources screen lists each source with its profile count, last import date, error count and removal count, and links to the Profiles list filtered by that source. It has no add, edit, approve, reject or publish. Source approval is enforced in the acquisition pipeline (REQ-136 to 138). | D-042 | M | SITE |
| REQ-244 | Roles are default bundles of named capabilities. The Administrator can grant or revoke individual capabilities for a user, across roles (for example give an Editor the Reviewer's bulk-unpublish capability, or a Reviewer an Editor capability). Menus, routes and actions follow the user's effective capabilities (default bundle plus grants, minus revocations). Every grant and revocation is audited and shown on a Users and capabilities screen. Capabilities not grantable to non-Administrators: users and roles, settings, the commercial register, plugin installation and the Nominations flag (Q72, accepted by default). | D-046 | M | SITE |
| REQ-245 | An email address belongs to exactly one user, and a user has exactly one role; capabilities adjust within that (REQ-244). Sign-in is by email, password and two-factor code, with no role selector; the role comes from the account. Only the Administrator creates users or changes a user's role, and both are audited. | D-048 | M | SITE |
