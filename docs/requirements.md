# WHOISWHO — Requirements

Derived from `docs/spec.md` (Venture Brief, sprint 4 Nov 2026 – 31 Jan 2027). Written 2026-11-06; updated after Gate 1 answers (see `docs/decisions.md`).

**Scope change (D-005):** the website processes no payments. Customers pay offline; admins record payments in a commercial register. All payment/checkout requirements are marked **Withdrawn** (numbers kept): REQ-011, 048, 070, 073, 074, 076, 097, 099, 113, 114, 117, 118, 119.
**Q7/Q8/C13 resolved (D-028):** there are no tiers, packages or entitlements. Every listing has the same format. Commercial relationships are admin-recorded arrangements. Withdrawn for this reason: REQ-010, 012, 017, 185.
**Q1 correction (D-029):** the demo site is public and indexable; profiles are visible only after human approval; removals return 410/noindex (REQ-208, 209).
**Q17 answered (D-030):** one login per profile owner; REQ-111 withdrawn for now.
**Publish first (D-033, 2026-11-08):** acquired records publish before human review, labelled and noindex, and every one is reviewed afterwards. REQ-148 and REQ-208 are amended; REQ-210 to REQ-228 are added in section 12. Requirements marked "Conflict with D-033" are unchanged until you rule.
**Pending (team lead):** Q16.

**Priority key:** `M` = MVP, built as early as possible (dates in the brief are ceilings, not targets) · `M (flag)` = built in the MVP but behind a feature flag that is OFF until the Venture CEO approves · `S` = should · `L` = later (post-sprint) · `Q#` = scope depends on that open question.
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

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-001 | The public site provides pages: Home, About, Discover People, Discover Businesses, Discover Institutions, Industries, Awards/Maiden Edition, Stories, Subscribe/Get Featured, Nominate, Contact. | 12.2 | M |
| REQ-002 | The home page states what WHOISWHO is, who it is for, and how to get listed, featured, nominated or recognised, without scrolling past the first screen on mobile. | 12.3 | M |
| REQ-003 | The system supports individual profiles with: full name, title, industry/expertise, biography, career history, achievements, education, affiliation, social/professional links, media appearances, awards, enquiry option. | 5.1 | M |
| REQ-004 | The system supports business profiles with: name, industry/sector, description, founding year, HQ and locations, leadership, products/services, achievements, website/social, business contact, media features. | 5.1 | M |
| REQ-005 | The system supports institutional profiles (education, associations, public bodies, NGOs) as a distinct type with the business fields plus mandate/type and a longer history section. | 5.1 | S |
| REQ-006 | Every published profile has a stable public URL, unique slug, page title/meta description, Open Graph tags and schema.org (Person/Organization) markup, and is listed in the sitemap. *(D-033: pending-review profiles have no sitemap entry or schema.org markup; REQ-217.)* | 5.3, 12 | M |
| REQ-007 | The directory can be browsed and filtered by industry, profession, sector, country, city, organisation type, professional category, achievement/recognition and award category. | 5.2 | M (industry, type, country, city, profession); S (rest) |
| REQ-008 | Visitors can search profiles by name, organisation or keyword and get ranked results. | 5.2 | M |
| REQ-009 | Admins can manage the controlled lists (industries, sectors, professions, organisation types, countries, cities) used by profiles and filters. | 5.2 | M |
| REQ-010 | **WITHDRAWN** (no tiers or packages; every listing has the same format; D-028). ~~An informational packages page describes each package, its inclusions and indicative pricing as admin-editable content. It has no cart and no checkout, and its call to action leads to the enquiry form (REQ-177).~~ | 6.1, 8 P1 | Withdrawn |
| REQ-011 | **WITHDRAWN** (no checkout; replaced by lead form REQ-177). ~~A prospective subscriber can choose a plan, create an account, pay and submit their profile in one continuous journey with no human intervention required.~~ | 12.3 | Withdrawn |
| REQ-012 | **WITHDRAWN** (no packages; D-028). ~~A corporate/institutional customer record can cover several profiles and users (configuration-driven; details pending Q7).~~ | 6.2 | Withdrawn |
| REQ-013 | The profile intake form validates input, saves drafts and accepts image uploads with enforced type and size limits. Customers reach it through an admin-issued invitation link after the offline agreement; there is no payment step. | 8 P1 | M |
| REQ-014 | A submitted profile enters an editorial review queue and is not public until a staff reviewer approves it. | 8 P2, 16 | M |
| REQ-015 | An owner can edit a published profile; changes to name, title, organisation, awards or achievement claims re-enter review before going live. | 6.1 | M |
| REQ-016 | Admins can mark any post or profile Sponsored (REQ-182). The label is distinct from any trust status (REQ-024). There is no paid placement tier. | 6.1, 3.4 | M |
| REQ-017 | **WITHDRAWN** (no paid tiers; every listing has the same format; D-028). ~~Paid tiers allow additional images/media on the profile.~~ | 6.1 | Withdrawn |
| REQ-018 | A visitor can send an enquiry to a profile owner through a relay form; the owner's email/phone is not exposed; enquiries are rate-limited and spam-protected; owners can switch enquiries off. | 5.1 | M |
| REQ-019 | A profile owner can see their profile status and view count. | 6.1 | S |
| REQ-020 | Profile analytics beyond view count (sources, enquiries over time). | 6.1 | L |
| REQ-021 | The system keeps an admin-managed commercial register (see REQ-179). | 9.3 | M |
| REQ-022 | Counting rule (brief section 9.3): a "paid subscription" for the sprint target is a paid arrangement with status Confirmed in the register. Confirmed requires amount, currency, payment method and reference, date received, a named confirmed-by admin, and onboarding started. Status is computed from fields, not edited by hand. Complimentary arrangements never count. | 9.3 | M |
| REQ-023 | Complimentary arrangements carry a flag, are excluded from paid counts and revenue, and are reported separately. | 9.3 | M |
| REQ-024 | Each profile shows exactly one trust status (see the status ladder in REQ-150). Sponsored, Award and Editorial markers are separate labels and never imply verification. | 3.4, 5.4 | M |
| REQ-025 | Verification follows a documented checklist per entity type; the system records who verified, when, and what evidence; payment status is never an input. | 3.4, 6.1 | M |
| REQ-026 | Sponsored articles and campaigns carry a visible "Sponsored" label on the page, listing cards and shares. | 6.3 | M |
| REQ-027 | Editors can create, schedule and publish articles (interview, feature, spotlight, opinion, announcement, news) with title, body, author, hero image, tags and SEO fields. | 5.3 | M |
| REQ-028 | Articles can be linked to profiles and industries; a profile page lists its related articles. | 5.3 | S |
| REQ-029 | A Stories page lists articles filterable by type and industry. | 12.2 | M |
| REQ-030 | A Nominate page collects: nominator identity, nominee identity, category, supporting evidence, and a conflict-of-interest declaration. | 7.4, 12.2 | M |
| REQ-031 | Staff can list, filter, export and set the status of nominations. | 7.4 | M |
| REQ-032 | The Awards section shows the edition, categories, eligibility criteria and the nomination/judging process, managed as content. | 5.4, 7.3 | M |
| REQ-033 | Category, nominee and winner pages exist; nominee pages appear only at the shortlist stage (REQ-198). | 12.2 | M (flag) |
| REQ-034 | The system tracks each nomination through the 10 stages of the recognition process: screening, shortlist, judging, final approval, notification, announcement. | 7.4 | M (flag) |
| REQ-035 | Judges have accounts and score assigned nominations against configurable criteria and weights (REQ-200). | 5.4 | M (flag) |
| REQ-036 | Judges and staff declare conflicts of interest; conflicted judges cannot see or score that nomination. | 5.4 | M (flag) |
| REQ-037 | Judge and scoring views never display payment, subscription, sponsorship or customer-record data of any nominee or nominator (see REQ-201). | 5.4, 7.4 | M (flag) |
| REQ-038 | Winner notification and public announcement can be prepared and published from the system, behind a flag (REQ-194). | 7.4 | M (flag) |
| REQ-039 | A Contact page sends messages to a staff inbox and logs them. | 12.2 | M |

## 2. Non-functional

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-040 | All public pages are responsive and usable at 360 px width. | 12.1 | M |
| REQ-041 | Profile and directory pages reach Largest Contentful Paint ≤ 2.5 s on a mid-range Android phone over throttled 4G. *(assumed target)* | 12 | M |
| REQ-042 | Images are served resized and in modern formats via a CDN. | 12.1 | M |
| REQ-043 | Public pages meet WCAG 2.1 AA. *(assumed target)* | 12 | M |
| REQ-044 | Public content is server-rendered or static so it is indexable; canonical URLs and sitemaps are provided. *(D-033: pending-review pages are noindex; REQ-217.)* | 5.3 | M |
| REQ-045 | Production availability ≥ 99.5% monthly; the enquiry form, directory and admin sign-in are monitored with alerting. *(assumed)* | — | M |
| REQ-046 | All traffic is HTTPS; OWASP Top-10 mitigations, security headers, form rate limits and dependency scanning are in place. | — | M |
| REQ-047 | Staff accounts require strong passwords and MFA. | — | M |
| REQ-048 | **WITHDRAWN** (no card handling on the site; PCI scope removed). ~~Card data never touches WHOISWHO servers (hosted checkout / tokenised).~~ | — | Withdrawn |
| REQ-049 | Database and uploads are backed up daily; a restore is tested before launch; RPO 24 h, RTO 8 h. *(assumed)* | — | M |
| REQ-050 | Errors, uptime and form-submission failures are monitored; logs contain no personal data beyond IDs. | — | M |
| REQ-051 | All staff/admin actions on profiles, customer records and reviews are written to an immutable audit log. *(D-033: AI actions too; REQ-228.)* | 15.7 | M |
| REQ-052 | Brand colours, type and the tagline "Influence. Achievement. Legacy." are implemented as design tokens so the approved identity can be applied or changed without rebuilding pages. | 2.2, 12.1 | M |
| REQ-053 | The site is English-only, handles names with diacritics (UTF-8), uses NGN formatting and the WAT time zone. | 7.2 | M |
| REQ-054 | The design handles ≥ 50 concurrent users and ≥ 100k page views/month on the launch infrastructure. *(assumed)* | — | M |
| REQ-055 | Supports the latest two versions of Chrome, Safari, Edge, Firefox and Android Chrome. | — | M |
| REQ-056 | A staging environment mirrors production; automated tests run on every PR. | — | M |

## 3. Data & privacy

*Nigeria Data Protection Act 2023 (NDPA) is assumed to apply; exact duties must be confirmed by Legal (Q5). This is not legal advice.*

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-057 | For each category of person whose data is held the lawful basis is recorded. For public figures published without consent the basis is legitimate interests with a right to object; Incspired Legal's written position is requested by 13 Nov and is not a build blocker (D-016). | 13.2 | M |
| REQ-058 | A privacy notice is shown at every point of collection; consent (where used) is stored with timestamp and policy version. | 12.2 | M |
| REQ-059 | The system supports data-subject requests (access, correction, erasure, objection, portability) with intake, tracking and due dates. | — | M |
| REQ-060 | Profile owners choose which contact details are public; the default is none beyond the enquiry form. | 5.1 | M |
| REQ-061 | Marketing email requires separate opt-in and every message has one-click unsubscribe. | 10.5 | M |
| REQ-062 | Non-essential cookies/analytics load only after consent. | — | M |
| REQ-063 | A retention schedule exists per data class and a job deletes or anonymises data when it expires. | — | S |
| REQ-064 | Nominator details are never public. Nominee data stays private until eligibility screening is complete; see REQ-197 and REQ-198. | 7.4 | M |
| REQ-065 | A register lists every processor and its location (hosting, email, analytics, AI provider), each with a data-processing agreement. | — | M |
| REQ-066 | A breach-response procedure exists (detect, log, notify the regulator and affected people within the legal deadline). | — | M |
| REQ-067 | The system does not collect or store sensitive personal data (health, religion, politics, ethnicity, biometrics, criminal records, sexual life, trade-union membership) in profiles, stubs or nominations; the intake forms exclude these and free text is checked. | — | M |
| REQ-068 | Individuals under 18 cannot hold a profile. | — | M |
| REQ-069 | Access is role-based and least-privilege; bulk export of personal data is limited to named roles and logged. | — | M |
| REQ-070 | **WITHDRAWN** (no payment data held by the site; offline payments are recorded in the register, REQ-179). ~~Only gateway reference IDs and amounts are stored for payments, never card numbers.~~ | — | Withdrawn |
| REQ-071 | A takedown/complaint process (defamation, privacy, IP) is published, tracked and has a response target. | 15.7 | M |
| REQ-072 | A data-protection impact assessment is completed and a data-protection contact designated, in parallel with the build. If Legal objects, the kill switch (REQ-193) is used. | — | M |

## 4. Integrations

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-073 | **WITHDRAWN** (no payment gateway; D-005). ~~A payment gateway supporting NGN cards and bank transfer is integrated via hosted checkout; webhooks are signature-verified and idempotent.~~ | 8 P1 | Withdrawn |
| REQ-074 | **WITHDRAWN** (superseded by the admin commercial register, REQ-179). ~~Payments made by bank transfer/invoice can be confirmed by Finance in the admin and recorded against a subscription.~~ | 9.3 | Withdrawn |
| REQ-075 | Transactional email is sent via a provider with SPF, DKIM and DMARC configured; bounces are recorded. | — | M |
| REQ-076 | **WITHDRAWN** (receipts and invoices are issued offline by Finance). ~~Receipts and invoices (PDF, with VAT where applicable) are generated for each payment.~~ | 9.3 | Withdrawn |
| REQ-077 | The commercial register, leads and enquiries can be exported/imported (CSV) for CaromHQ; a live integration is built only if CaromHQ exposes an API. | 15.5 | Q16 |
| REQ-078 | Privacy-friendly web analytics measure visits, journey completion and conversions. | 14.3 | M |
| REQ-079 | Search is provided by the database or a hosted search service sized for ≥ 10k profiles. | 5.2 | M |
| REQ-080 | Uploaded media is stored in object storage behind a CDN. | 12.1 | M |
| REQ-081 | Public forms are protected by a bot challenge. | — | M |
| REQ-082 | Pages expose social share cards; social links are displayed as links only (no social-platform API use). | 10.5 | M |

## 5. Admin & ops

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-083 | An admin console with roles: Admin, Editor, Reviewer, Sales/Support, Finance, Judge. *(D-033: plus two non-human roles; REQ-219.)* | 13 | M |
| REQ-084 | A review queue shows each pending item with a field-level diff, sources and approve / edit / reject-with-reason actions. *(D-033: also used after publication; REQ-222.)* | 8 P2 | M |
| REQ-085 | Staff can create or edit a profile on behalf of a subscriber or as a complimentary listing; these are flagged and attributed. | 9.3 | M |
| REQ-086 | Admins can edit a customer record's status, extend or end a term, or mark it complimentary; each change requires a reason and is audited. Refunds and cancellations are handled offline by Finance and recorded as a note and status only. | — | M |
| REQ-087 | The commercial register exports to CSV with the counting-rule status of each row. | 9.3, 14 | M |
| REQ-088 | A weekly dashboard shows: paid subscriptions, revenue, average value, profiles submitted/published, journey completion rate, visitors, enquiries, nominations. | 14.2–14.5 | M |
| REQ-089 | A support inbox tracks enquiries, claims and complaints with owner, status and age. | 8 P2 | M |
| REQ-090 | Any visitor can report a profile; reports enter an abuse queue visible to staff. | 15.7 | M |
| REQ-091 | Staff can unpublish or hide any profile or article immediately. | 15.7 | M |
| REQ-092 | Staff can bulk-import profiles from CSV with validation and a dry-run, tagged with source and importer. | 8 P3 | M |
| REQ-093 | Prospect lists can be imported/exported (CSV) in a store separate from public data (see REQ-176). | 9.2 | S |
| REQ-094 | Email notifications fire for: enquiry received (to sales), profile received, approved, rejected with reason, nominee invitation, claim received, claim resolved. Renewal and expiry reminders go to admins only. | 8 P2 | M |
| REQ-095 | An operations runbook documents deploy, rollback, backup restore and incident handling. | 15.5 | M |

## 6. Timeline-driven

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-096 | By **20 Nov 2026**: homepage, core landing pages and the agreed site structure are viewable (staging acceptable). | 8 P1 | M |
| REQ-097 | **WITHDRAWN** (no payment integration to test; D-005). ~~By **20 Nov 2026**: subscription packages are configured and payment + confirmation work end-to-end in the gateway's test mode.~~ | 8 P1 | Withdrawn |
| REQ-098 | By **20 Nov 2026**: individual, business and institutional profile templates and the directory structure exist. | 8 P1 | M |
| REQ-099 | **WITHDRAWN** (no live payments; REQ-177 and REQ-179 must be live instead). ~~By **21 Nov 2026**: live-mode payments and confirmation emails work, because direct outreach and "secure the first paid subscriptions" start that day.~~ | 8 P2 | Withdrawn |
| REQ-100 | By **21 Nov 2026** (ceiling): Terms, Privacy Policy and commercial terms are published before any customer data is collected. | 12.2 | M |
| REQ-101 | By **20 Dec 2026** (ceiling): first paying customers have their arrangements recorded and profiles reviewed and published, and the support process works. | 8 P2 | M |
| REQ-102 | By **20 Dec 2026**: weekly reporting (REQ-088) is live. | 14.6 | M |
| REQ-103 | By **31 Jan 2027** (ceiling): the website, enquiry journey and admin register operate in production. | 8 P3 | M |
| REQ-104 | By **31 Jan 2027**: the directory is populated to the volume decided in Q1. | 8 P3 | Q1 |
| REQ-105 | By **31 Jan 2027** (ceiling, not target): the sprint target is met if either condition holds: ≥ 10 paid subscriptions (counted under REQ-022) or NGN 5m confirmed revenue (all streams, REQ-180). | 8 P3, 9 | M |
| REQ-106 | The Nominate page shows "Nominations open soon" with an email sign-up while the nominations flag is OFF (REQ-194). The flag is turned on only with the Venture CEO's explicit approval after the award rules and eligibility page are published. | 8 P2, C9 | M |

## 7. [implied] but unstated

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-107 | Users can register with email verification. | — | M |
| REQ-108 | Users can log in, log out, reset passwords, and see/revoke sessions. | — | M |
| REQ-109 | Roles are enforced: visitor, profile owner (one login per profile), staff roles, judge. | — | M |
| REQ-110 | A user can deactivate or delete their account; the effect on their profile and arrangements is defined and shown before confirming. | — | M |
| REQ-111 | **WITHDRAWN** (deferred: one login per profile owner for now; D-030). ~~An organisation account supports multiple users (one owner, several editors).~~ | 6.2 | Withdrawn |
| REQ-112 | Money is stored as integer minor units with a currency code (default NGN). Revenue is recorded by stream (REQ-180). | — | M |
| REQ-113 | **WITHDRAWN** (no online payments to retry). ~~Failed or abandoned payments can be retried via a link; no profile is published without confirmed payment.~~ | 9.3 | Withdrawn |
| REQ-114 | **WITHDRAWN** (no webhooks to reconcile). ~~Gateway webhooks are reconciled against the register daily; duplicates and mismatches are flagged.~~ | — | Withdrawn |
| REQ-115 | Refunds and cancellations are handled offline by Finance; the platform stores status and notes only. | — | M |
| REQ-116 | Each arrangement stores start and end dates; admins see expiry reminders (REQ-189). When an arrangement ends, the Sponsored label it set is removed automatically; no profile or post is deleted. | — | M |
| REQ-117 | **WITHDRAWN** (no online plan changes; tier label and expiry are admin-edited, REQ-185). ~~Plan upgrades/downgrades follow a defined proration rule.~~ | — | Withdrawn |
| REQ-118 | **WITHDRAWN** (no promo codes at checkout; complimentary flag in REQ-179). ~~Promo codes and complimentary grants can be created by Admin.~~ | 9.3 | Withdrawn |
| REQ-119 | **WITHDRAWN** (offline invoicing is done by Finance outside the platform). ~~Corporate customers can be invoiced and pay by bank transfer.~~ | 6.2 | Withdrawn |
| REQ-120 | Terms, Privacy, Cookie policy, commercial terms and Awards rules are published. | 12.2, 13.2 | M |
| REQ-121 | 404/500 pages, robots.txt and sitemap.xml exist. | — | M |
| REQ-122 | Public forms are rate-limited per IP and per account. | — | M |
| REQ-123 | The system warns about likely duplicate profiles at submission and in review. | — | M |
| REQ-124 | A process exists for two parties claiming the same profile (ownership dispute). | — | M |
| REQ-125 | Profile edits keep version history with author and timestamp. | 15.7 | M |
| REQ-126 | Submitted content is covered by a licence and accuracy warranty accepted at submission. | — | M |
| REQ-127 | Uploaded files are type-checked and scanned. | — | M |
| REQ-128 | Owners can preview a draft exactly as it will be published. | — | S |
| REQ-129 | Slug changes create redirects from the old URL. | — | S |
| REQ-130 | A public "How WHOISWHO compiles profiles and what each label means" page exists. | 3.4 | M |
| REQ-131 | The venture can export all its own data (profiles, register, content) in open formats. | 13.1 | S |

## 8. Data acquisition and population (Nigeria first)

### 8.1 Assessment of the four approaches

| | Approach | Verdict | Why |
|---|---|---|---|
| A | Organisations from sanctioned public sources | **Do in MVP** | Mostly company facts, low data-protection risk, many structured sources, and a legitimate "Unclaimed" starting point for sales. Risk: stale or wrong data, source licence terms. |
| B | Scraping for prospecting only | **Not a product feature** | A sales-research task. Product needs only CSV import/export and a hard wall between prospect data and public data (REQ-093, REQ-176). Use licensed tools or manual research; tool terms still need checking. |
| C | Public "unclaimed" stubs of individuals | **Defer unless Legal signs off (Q2, Q5)** | Publishing personal data of people who did not ask to be listed is the highest legal and reputational risk (NDPA, defamation, harm if wrong). Safer variant: pre-built profile shown only to the person via private claim link; published only on consent. |
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

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-132 | Organisation stubs (approach A) can be created from approved sources and published as Unclaimed after review. *(D-033: may publish before human review; REQ-214.)* | brief | M |
| REQ-133 | Individual stubs (approach C) are enabled in the MVP and published without the individual's consent under the rules in REQ-139 to REQ-176, with the kill switch REQ-193. | brief | M |
| REQ-134 | Bulk-import adapters exist for CSV/JSON and for the Wikidata SPARQL API; other sources are entered manually. | brief | M |
| REQ-135 | Wikidata-sourced records store the QID and are re-synced on request. | brief | S |
| REQ-136 | A source registry records for each source: name, entity types, URL, access method (API / export / crawl / manual), licence and terms summary, robots.txt check date, approver, status. | brief | M |
| REQ-137 | Ingestion is permitted only from sources with status Approved; Approved requires a named person to have checked terms, licence and robots rules. | brief | M |
| REQ-138 | No automated collection from sites whose terms or robots rules forbid it, nor from login-walled sites or social platforms (including LinkedIn); any crawler identifies itself, rate-limits itself and never circumvents blocks. | brief | M |
| REQ-139 | Individual stubs hold only these fields: name, current title, current organisation, industry, public-record roles (board, office), published awards, official or organisational website. | brief | M |
| REQ-140 | Never stored for individuals: home address, phone, personal email, family, date of birth or age, health, religion, politics, ethnicity, finances, allegations, or personal social handles. The schema offers no field for them. | brief | M |
| REQ-141 | Free-text fields in any stub or draft are scanned for patterns (phones, emails, addresses, ages, sensitive keywords) and blocked from review until cleared. *(D-033: also blocked from publishing; REQ-212.)* | brief | M |
| REQ-142 | Every field on an acquired record stores: source URL, retrieval date, confidence (high / medium / low) and method (API / import / manual / AI). | brief | M |
| REQ-143 | Fields with low confidence or no source cannot be published. | brief | M |
| REQ-144 | Public stub pages show the source link for key facts and a "last reviewed" date. *(D-033: pending profiles show the REQ-216 label instead of a date.)* | brief | M |
| REQ-145 | Each organisation and individual has one persistent ID; matching uses RC number, Wikidata QID, and normalised name + organisation + location. | brief | M |
| REQ-146 | Probable duplicates are queued for human merge review; individuals are never auto-merged. | brief | M |
| REQ-147 | A suppression list holds people and organisations who asked for removal; imports skip them. | brief | M |
| REQ-148 | **AMENDED by D-033.** All acquired records pass the publish gate (REQ-212) from the staging database (REQ-211) and publish under the tier rules (REQ-214). A human reviews every published record afterwards in the review queue (REQ-084, REQ-222) with sources visible. A record stays Published – review pending and noindex until a human approves it. The approving reviewer is not the importer or drafter. ~~All acquired records go to the editorial review queue (REQ-084) with sources visible. 100% human review before publishing (no sampling); the approving reviewer is not the importer or drafter.~~ | brief | M |
| REQ-149 | Review throughput, queue age and rejection reasons are tracked. | brief | M |
| REQ-150 | The status ladder is Unclaimed → Claimed → Verified → Featured; transitions are logged with actor and reason. | brief | M |
| REQ-151 | All acquired stubs carry a visible "Unclaimed" label explaining that WHOISWHO compiled it from public sources. | brief | M |
| REQ-152 | Claimed requires proof the claimant is the person or an authorised representative of the organisation. | brief | M |
| REQ-153 | Verified requires the checklist in REQ-025 and is independent of payment. | 3.4 | M |
| REQ-154 | Featured is an editorial decision recorded with reason; it cannot be bought. | 3.4 | Q11 |
| REQ-155 | Sponsored is a separate label that can sit on a profile of any status, does not alter the status, and is never styled like Verified. | 3.4, 6.3 | M |
| REQ-156 | Every stub has "Claim this profile", "Suggest a correction" and "Request removal" actions. | brief | M |
| REQ-157 | Correction and removal requests do not require an account or payment. | brief | M |
| REQ-158 | Response targets: acknowledge within 1 business day; hide the profile within 2 business days of a valid removal request from the subject; resolve corrections within 7 days. Breaches are flagged. | brief | M |
| REQ-159 | Where lawful and a business contact exists, the subject is told when a stub is created (as decided by Legal in Q5). | brief | Q5 |
| REQ-160 | Unclaimed records are re-checked every 6 months, flagged stale at 12 months, and unpublished if not re-reviewed by 18 months; claimed owners are prompted yearly. | brief | S |
| REQ-161 | Records synced from external sources flag source changes for review rather than overwriting. | brief | S |
| REQ-162 | Stub profiles show no photograph unless it is owner-supplied or licensed. | brief | M |
| REQ-163 | **Conflict with D-033: see Q46.** Reviewers apply a neutral-content checklist: no allegations, litigation, rumours, opinion or personal life; only dated, sourced, professional facts. | brief | M |
| REQ-164 | A corrections log records every change made after publication on a stub, with the reason. | brief | M |
| REQ-165 | AI drafting is allowed for organisation and individual profiles under REQ-166 to REQ-170 and is controlled by a feature flag. | brief | M |
| REQ-166 | The model may only restate facts found in the fetched source text. Every claim is mapped to a stored source passage; any claim without a mapping is dropped automatically. | brief | M |
| REQ-167 | AI output uses a neutral, factual tone with no praise or superlatives, and makes no claims about legal trouble, health, family, finances or political views. | brief | M |
| REQ-168 | **Conflict with D-033: see Q46.** A human reviewer approves every AI-drafted profile and sees each claim next to its source. Profiles record internally that they were AI-assisted, plus model, prompt version and source hash. | brief | M |
| REQ-169 | The AI provider's API terms exclude training on our data; Legal confirms the data transfer; the provider is in the processor register (REQ-065). | brief | M |
| REQ-170 | **Must now describe publish first: see Q35.** The public disclosure page states how AI-assisted profiles are produced and reviewed (wording as Legal decides). | brief | M |
| REQ-171 | A dashboard reports profiles by entity type, source, status and age. | brief | S |
| REQ-172 | A subject may add a short right-of-reply note to a profile or editorial item, subject to review. | brief | S |
| REQ-173 | Claimed profile owners can see which sources the platform used about them. | brief | S |
| REQ-174 | Disputes (claim, correction, removal) escalate to a named Editor-in-Chief role. | brief | M |
| REQ-175 | Complimentary profiles created for a customer require that customer's recorded consent before publication. (Stubs follow REQ-133 instead.) | 9.3 | M |
| REQ-176 | Prospect data (approach B) lives in a separate store, is never public, and cannot become a profile except by entering the REQ-148 review path with an approved source. | brief | M |

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
| N21 | After any removal or kill-switch use, request search-engine removal/refresh (e.g. Search Console removals) for already indexed URLs; the platform returns 410 and noindex meanwhile | D-029 | Editorial / admin |

## 10. Added after Gate 1 (scope change and decisions)

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-177 | The "Subscribe / Get Featured" page is an enquiry form (name, organisation, interest, contact) that creates a lead, plus informational content about being listed, sponsored posts and profiles, media/editorial work and awards sponsorship. No packages, tiers, cart or checkout. The form is spam-protected and rate-limited. | 12.2, D-005 | M |
| REQ-178 | Sales staff can manage leads: owner, status, notes, conversion to a customer and arrangement, CSV export. | D-005 | M |
| REQ-179 | Admins can create and edit arrangements in the commercial register. Fields: type (sponsored post, sponsored profile, media/editorial package, awards sponsorship, other), customer (with segment), amount, currency, payment method, payment reference, date received, confirmed-by, status (proposed, awaiting payment, confirmed, ended, cancelled), start date, end date, complimentary flag, notes. | 9.3, D-006, D-028 | M |
| REQ-180 | Revenue is reported by arrangement type and by revenue stream (subscription, media/editorial, sponsorship). Each arrangement type has an admin-editable default stream. | 6, C8 | M |
| REQ-181 | The admin dashboard shows both sprint numbers (count of Confirmed paid arrangements under REQ-022, with a breakdown by type; confirmed revenue total) and revenue per stream. Thresholds (10 and NGN 5m) are configuration; no single gate is hard-coded. | 8, 9, C8 | M |
| REQ-182 | Admins can mark any post or profile Sponsored with a sponsor name; the page, listing card and share preview show a visible disclosure label. A sponsored-post or sponsored-profile arrangement can be linked to the item and applies the label between its start and end dates; admins may also set it manually. | 6.3, D-006 | M |
| REQ-183 | Admins can set Featured (editorial) with a recorded reason, and Verified via the checklist of REQ-025. Neither can be set from an arrangement or payment field. | 3.4 | M |
| REQ-184 | Changing commercial data (arrangement, payment, sponsorship) never changes Verified or Featured, and the reverse; automated tests cover this. | 3.4 | M |
| REQ-185 | **WITHDRAWN** (no tiers, plans or entitlements; commercial relationships are arrangements, REQ-179; D-028). ~~Tier label and expiry are admin-editable per profile. Plans, tiers and entitlements are stored as configuration, not code.~~ | 6.1, Q7/Q8 pending | Withdrawn |
| REQ-186 | Sponsors are records (name, logo, link) that admins can attach to awards, categories and pages. | 6.4 | M |
| REQ-187 | Admins can keep internal notes on profiles, customers, leads and nominations; notes are never public and never visible to judges. | D-006 | M |
| REQ-188 | Every change to arrangements, Sponsored/Featured/Verified labels, sponsors and notes is written to the audit log (extends REQ-051) with actor, time, old and new values. | 15.7 | M |
| REQ-189 | Admins see reminders for upcoming expiries; the system takes no automatic action against customers. | Q10 | M |
| REQ-190 | All brand values (colours, fonts, logo, spacing) live in one central theme config; no brand values are hard-coded elsewhere, and a check fails the build if they are. | C5 | M |
| REQ-191 | **Conflict with D-033: see Q49.** The target is about 100 organisation and about 100 individual reviewed profiles live. The demo/staging site is public and indexable by search engines; there is no private preview and no login protection. | Q1 | M |
| REQ-192 | Sourcing of further organisations and individuals continues in parallel through the approved pipeline. | Q1 | M |
| REQ-193 | A global kill switch unpublishes all unclaimed individual profiles at once and works on public, indexed pages (REQ-209). It is reversible, restricted to named roles, and audited. *(D-033: REQ-227 covers pending organisations.)* | Q2 | M |
| REQ-194 | Feature flags for nominations, judging and public announcements default OFF; switching one on requires the Admin role, records the CEO's approval reference, and is audited. | C9, Q12 | M |
| REQ-195 | While nominations are off, the Nominate page shows "Nominations open soon" with an email sign-up (consent recorded, unsubscribe supported). | C9 | M |
| REQ-196 | Anyone may nominate. The nominator's email is verified; submissions are rate-limited with duplicate detection; there is no public voting. | Q15 | M (flag) |
| REQ-197 | Nominations are private until eligibility screening is complete; after screening the nominee is emailed a link to accept, decline or correct their details. | Q15 | M (flag) |
| REQ-198 | Public nominee pages appear only at the shortlist stage, built from the reviewed profile and sourced facts. A nominee who declines is removed and added to the suppression list (REQ-147). | Q15 | M (flag) |
| REQ-199 | Payment, subscription or sponsorship never creates a nomination or affects screening or shortlisting; there is no data path from commercial records into the awards workflow. | 5.4 | M |
| REQ-200 | Admins configure categories, eligibility criteria, scoring criteria and weights, judges per category and shortlist size; none are hard-coded. | Q12 | M (flag) |
| REQ-201 | Judge accounts see only assigned nominations and never payment, subscription or sponsorship data. | Q12 | M (flag) |
| REQ-202 | Eligibility screening, shortlisting and final approval are separate recorded steps with named approvers. | Q12 | M (flag) |
| REQ-203 | Images under open licences (e.g. Wikimedia Commons) may be used when the licence and attribution are stored with the image and shown on the page; no other third-party photos. | Q22 | M |
| REQ-204 | The domain is wiwi.africa, including the email sending domain with SPF, DKIM and DMARC. | C1 | M |
| REQ-205 | Customers, leads and enquiries have stable external IDs and change timestamps so a CaromHQ integration can be added later; CSV import/export works meanwhile (REQ-077). | C12 | M |
| REQ-206 | The review queue shows per-reviewer workload and warns when backlog exceeds capacity so a third reviewer can be added. *(D-033: the backlog is now the post-publication queue; REQ-222, REQ-225.)* | Q4 | M |
| REQ-207 | The suppression list is permanent, cannot be bypassed by any import, and covers removed individuals, declined nominees and removed organisations. | Q2, Q15 | M |

## 11. Added after Q1/Q7 corrections

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-208 | **AMENDED by D-033.** A profile is publicly visible only in state Published – review pending or Published – reviewed (REQ-210). Staged, held, rejected, taken-down and unpublished profiles are visible to admin roles only. Customer-submitted profiles and owner edits are public only after a human approves them (REQ-014, 015). This holds across pages, search, sitemaps, feeds, APIs, previews and caches, and automated tests cover every state on every surface. ~~A profile is publicly visible only after a human reviewer approves it. Drafts, unreviewed, rejected and AI-drafted-pending profiles are visible to admin roles only. This holds across pages, search, sitemaps, feeds, APIs, previews and caches, and automated tests cover each.~~ | Q1 | M |
| REQ-209 | Unpublishing is immediate and works on public, indexed pages: removal, suppression, nominee decline and the kill switch make the URL return 410 Gone with a noindex header, drop it from the sitemap and internal search, and purge it from the CDN/cache. Restoring after a reversible unpublish returns 200. | Q1, Q2 | M |

## 12. Added for publish first (D-033)

These apply to acquired records (REQ-132, REQ-133). Customer submissions, owner edits, complimentary listings and nominee pages stay approval-first (REQ-014, 015, 175, 198) unless Q48 rules otherwise. They are the WordPress build's input (D-031). Numbers and limits marked "default" are proposals awaiting Q50–Q52 and are stored as settings, not code.

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-210 | Every profile has exactly one publication state, stored separately from its trust status (REQ-150): Staged, Awaiting quick look, Held amber, Held red, Published – review pending, Published – reviewed, AI takedown, Unpublished, Rejected, Removed and suppressed. Only the two Published states are public. Only the transitions in the table below are allowed; any other attempt is refused and logged. | D-033 | M |
| REQ-211 | Pipeline output is held in a staging database that no public page, feed, API or search reads. Each candidate version there has a content hash. Personal data in it follows the same rules as live data (REQ-063, REQ-140). See Q53. | D-033 | M |
| REQ-212 | Code, not the model, decides publication. In one transaction the publish gate requires: source Approved (REQ-137); every field sourced and not low confidence (REQ-143); sensitive scan clear (REQ-141); quote verification passed (REQ-166); AI audit passed (REQ-213); inclusion criterion evidence present; suppression check clear (REQ-207); under-18 guard (REQ-068); no pause in force (REQ-224); under the cap (REQ-225); content hash equal to the version checked (REQ-221). Any failure blocks publication and logs the reason. | D-033 | M |
| REQ-213 | Every record gets an AI audit before publication, including records whose text is not AI-drafted. The audit is a separate model run that sees only the stored source passages and the draft, and returns a verdict per claim (supported, identity consistent, neutral, no prohibited topic). It can only lower a record's colour, never raise it. Model, prompt version and verdict are stored. | D-033 | M |
| REQ-214 | The gate gives each record a colour. Green: all checks and the audit pass. Amber (waits for full human review, then goes live as reviewed): medium-confidence field, audit unsure, probable duplicate, sources disagree, an individual's current role not stated on an official page fetched in the last 30 days, the first 10 records from a new source, a current public office-holder, a source that cannot be monitored automatically. Red (not published until a human clears it): suppressed match, sensitive or prohibited content, an unsupported claim, low-confidence or unsourced field, no criterion evidence, unapproved source, possible minor. Green structured-source organisation records with no named person publish automatically. Green individuals and entrepreneurs wait for a quick look (REQ-215). AI-drafted organisation records follow Q46; individuals may change under Q47. | D-033 | M |
| REQ-215 | The quick-look screen shows name, organisation, role, the evidence page and the inclusion criterion, with a timer. The person checks four things: right person, criterion met, role still current on the evidence page, nothing prohibited at a glance. Decisions: go live, hold for full review, reject. A quick look is not a review: the profile stays Published – review pending. The person is not the importer. | D-033 | M |
| REQ-216 | A Published – review pending profile shows, on its page, on any card and in its share preview, the label "Compiled automatically from public sources. Human review pending." with a correct/remove link and a link to the how-we-compile page (REQ-130). After human approval it shows "Reviewed on <date>", which satisfies REQ-144. The label is not a trust status (REQ-024), is shown alongside Unclaimed (REQ-151) and is never styled like Verified. The wording is one configuration value so Legal can change it. | D-033 | M |
| REQ-217 | A Published – review pending profile returns HTTP 200 with an `X-Robots-Tag: noindex` header and a robots noindex meta tag. It has no sitemap entry, feed entry or schema.org markup, including anything generated by a plugin. robots.txt must not block these URLs, because a crawler has to read the page to see the rule. On human approval the noindex is removed, markup and sitemap entry are added, and the cache is purged in the same flow. Automated tests cover every state on every surface (extends REQ-208). | D-033 | M |
| REQ-218 | Indexable listing pages (home, discover, industry) show reviewed profiles only, so a pending profile's name and title cannot be indexed through a list. Pending profiles are reachable by direct URL, on-site search and a noindex "include profiles pending review" view, always with the label. See Q54. | D-033 | M |
| REQ-219 | Two non-human WordPress roles have separate credentials. AI Publisher may only move a record to Published – review pending. AI Monitor may only move a published profile to AI takedown and restore it under REQ-221. Neither may edit any field, approve a review, or change trust status, labels, flags, thresholds, the cap, suppression or users. Credentials are scoped, revocable service tokens held by code, never passed to the model, rotated and restricted by source address. Staff MFA (REQ-047) does not apply to them. | D-033 | M |
| REQ-220 | The AI Monitor may take down a profile with a reason code from a fixed list. Takedown is reversible and behaves as REQ-209 (410, noindex, sitemap and search removal, purge). The public page never shows or implies the reason. Every takedown enters a queue for human review, due in 1 business day for S1-class codes and 2 for others, with the outcome uphold, overturn or correct. By default the monitor takes down pending profiles for any code, and reviewed, claimed, verified or sponsored profiles for S1-class codes only; other findings on those are flagged for a human (Q52). | D-033 | M |
| REQ-221 | What is live must be the exact version checked, confirmed by content hash. Republishing requires a hash-checked version and a full pass of the publish gate. The AI may restore only after a technical reason code (for example a dead source that has recovered); every other code needs a human. The AI never edits facts. An automatically redrafted version never replaces a reviewed profile without human approval (REQ-161). Items reviewed in review packs import only if their hash matches the approved hash. | D-033 | M |
| REQ-222 | Every published record is reviewed by a human in the review queue, with each claim beside its source (REQ-084, 163, 168). Approve sets Published – reviewed with the date; reject makes it 410. Queue order: S1 flags, measurement sample, profiles with a claim or correction, then oldest. The sample is the first 100 records of each tier, then a random 20% of each lot (at least 20 records), due within 3 business days, with the AI audit hidden from the reviewer. The reviewer records any error severity (S1, S2, S3) and is not the importer. | D-033 | M |
| REQ-223 | A monitor runs continuously on published profiles: source link health, source content change, prohibited-topic scan, conflicts between sources, plus suppression re-check and a heartbeat. It obeys REQ-138 for every fetch, and fetched text is treated as data, never instructions. Findings flag or take down; they never overwrite a field (REQ-161). Cadence is a setting, by default daily or weekly for pending and weekly or monthly for reviewed (plan section 6.8). | D-033 | M |
| REQ-224 | New publishing pauses automatically when a threshold in plan section 6.1 is passed (defaults: any S1; individuals 3 or more S2 in the last 100 reviewed; organisations 4 or more; 2 or more in the first 30 of any tier; sample overdue; oldest pending older than 21 days; monitor heartbeat missed for 48 hours). A pause can be per tier, per source or global, blocks REQ-212, and is lifted only by a named human with a reason. Thresholds are settings. A pause never unpublishes anything by itself except the S1 profile. | D-033 | M |
| REQ-225 | Pending profiles are capped. Default: the lower of 400 or two weeks of measured review capacity, with individuals and entrepreneurs at no more than 150 (or the same proportion of a lower cap). Records beyond the cap stay in Staged. Each ramp stage (50, 150, 300, then the cap) is opened by a named human after the previous stage's sample passes. | D-033 | M |
| REQ-226 | A pending profile cannot be Verified, Featured or Sponsored, cannot be linked to an arrangement, and cannot appear on an awards or nominee page. A claim or correction moves it to the top of the review queue. A removal request on a pending profile hides it at once (reversible, code SUBJECT_REQUEST), with rate limiting against abuse; REQ-158 deadlines still apply to acknowledging and deciding it. | D-033 | M |
| REQ-227 | A restricted bulk action unpublishes all pending profiles, organisations included, with the same effect and audit as REQ-193. It fills the gap that the kill switch covers unclaimed individuals only. | D-033 | M |
| REQ-228 | Every AI publish, takedown, restore, audit verdict, gate result, pause and cap event is written to the audit log with the service role, reason code and content hash (extends REQ-051, 188). The dashboard shows live, reviewed and pending counts by tier, oldest pending, error rates against thresholds, pause state, takedowns and their outcomes, and monitor heartbeat (extends REQ-149, 171). | D-033 | M |

### 12.1 Publication states

| State | Public? | Response | Search engines | Public label |
|---|---|---|---|---|
| Staged, Awaiting quick look, Held amber, Held red, Rejected | No | 404 | Not indexable | None |
| Published – review pending | Yes | 200 | noindex; no sitemap or schema.org | "Compiled automatically from public sources. Human review pending." with correct/remove link |
| Published – reviewed | Yes | 200 | Indexable; sitemap and schema.org | "Reviewed on <date>" |
| AI takedown, Unpublished | No | 410 (restore gives 200) | noindex | None; no reason shown |
| Removed and suppressed | No | 410, permanent | noindex | None |

### 12.2 Who may move a profile between states

| From | To | Actor | Condition |
|---|---|---|---|
| Staged | Awaiting quick look, Held amber, Held red, Published – review pending | Publish gate (code), through AI Publisher for the last | REQ-212, REQ-214 |
| Awaiting quick look | Published – review pending | Human starts it, AI Publisher completes it | Publish gate still passes |
| Awaiting quick look | Held amber, Rejected | Human | Quick look (REQ-215) |
| Held amber, Held red | Published – reviewed, Rejected, or back to Staged | Human | Full review; publish gate still passes |
| Published – review pending | Published – reviewed, or Rejected (410) | Human | REQ-222 |
| Either Published state | AI takedown | AI Monitor | REQ-220 |
| AI takedown | Previous Published state | Human; AI Monitor only for technical codes | REQ-221 |
| AI takedown | Unpublished, Rejected, Removed and suppressed | Human | REQ-220 |
| Any public state | Unpublished | Staff, kill switch, REQ-227 | REQ-091, 193, 209 |
| Any state | Removed and suppressed | Staff | REQ-158, 207 |
