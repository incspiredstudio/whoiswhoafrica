# WHOISWHO — Requirements

Derived from `docs/spec.md` (Venture Brief, sprint 4 Nov 2026 – 31 Jan 2027). Written 2026-11-06. **Draft — pending answers in `docs/questions.md`.**

**Priority key:** `M` = proposed MVP (must exist by 31 Jan 2027 unless a date says otherwise) · `S` = should, if capacity allows · `L` = later (post-sprint) · `Q#` = scope depends on that open question.
"Source" = spec section. `—` means not stated in the spec (inferred; see group 7).

---

## 0. Classification of the spec

The spec is mostly a **business, brand and sales plan**. Software is a minority of it.

| Spec section | Type | Notes |
|---|---|---|
| 1, 2, 3 Intro, brand, values | Non-software | Shapes copy, design, editorial rules. |
| 4 Audiences | Input | Drives roles and profile types. |
| 5.1 Profiles, 5.2 Directory, 5.3 Editorial | **Software** | Core build. |
| 5.4 Awards | Mixed | Governance is non-software; nominations/judging tooling is optional software (Q12). |
| 5.5 Events | Non-software | Tickets, tables, venues — manual for now. |
| 5.6 Intelligence | Out of scope | Spec says "not a requirement for the first three-month sprint". |
| 6 Revenue streams | Mixed | 6.1 profile subscriptions = software; 6.3 labelling = software; 6.2, 6.4, 6.5 = sales/ops. |
| 7 Maiden edition | Mixed | Categories, judging panel, event = non-software; nomination flow = software. |
| 8 Sprint plan | Timeline | Source of date-driven requirements. |
| 9 Target / 9.3 counting rule | Mixed | Subscription register + counting rule = software. |
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
| REQ-006 | Every published profile has a stable public URL, unique slug, page title/meta description, Open Graph tags and schema.org (Person/Organization) markup, and is listed in the sitemap. | 5.3, 12 | M |
| REQ-007 | The directory can be browsed and filtered by industry, profession, sector, country, city, organisation type, professional category, achievement/recognition and award category. | 5.2 | M (industry, type, country, city, profession); S (rest) |
| REQ-008 | Visitors can search profiles by name, organisation or keyword and get ranked results. | 5.2 | M |
| REQ-009 | Admins can manage the controlled lists (industries, sectors, professions, organisation types, countries, cities) used by profiles and filters. | 5.2 | M |
| REQ-010 | A plans page lists each subscription package, its inclusions, its price in NGN and its billing period. | 6.1, 8 P1 | M |
| REQ-011 | A prospective subscriber can choose a plan, create an account, pay and submit their profile in one continuous journey with no human intervention required. | 12.3 | M |
| REQ-012 | A corporate/institutional package lets one organisation hold several profiles and several users under one subscription. | 6.2 | Q7 |
| REQ-013 | The profile intake form validates input, saves drafts, and accepts image uploads with enforced type and size limits. | 8 P1 | M |
| REQ-014 | A submitted profile enters an editorial review queue and is not public until a staff reviewer approves it. | 8 P2, 16 | M |
| REQ-015 | An owner can edit a published profile; changes to name, title, organisation, awards or achievement claims re-enter review before going live. | 6.1 | M |
| REQ-016 | Paid "featured directory placement" is displayed with a "Sponsored/Promoted" label distinct from any trust status (see REQ-024). | 6.1, 3.4 | Q11 |
| REQ-017 | Paid tiers allow additional images/media on the profile. | 6.1 | S |
| REQ-018 | A visitor can send an enquiry to a profile owner through a relay form; the owner's email/phone is not exposed; enquiries are rate-limited and spam-protected; owners can switch enquiries off. | 5.1 | M |
| REQ-019 | A profile owner can see their profile status, subscription status, expiry and view count. | 6.1 | S |
| REQ-020 | Profile analytics beyond view count (sources, enquiries over time). | 6.1 | L |
| REQ-021 | The system keeps a subscription register: customer, package, amount, payment reference, payment date, onboarding status. | 9.3 | M |
| REQ-022 | A subscription is counted toward the target only when: package selected, payment confirmed, entered in the register, and onboarding started; the count is computed by that rule, not edited by hand. | 9.3 | M |
| REQ-023 | Complimentary listings are flagged and are excluded from the paid count and revenue; they are reported separately. | 9.3 | M |
| REQ-024 | Each profile shows exactly one trust status (see the status ladder in REQ-150). Sponsored, Award and Editorial markers are separate labels and never imply verification. | 3.4, 5.4 | M |
| REQ-025 | Verification follows a documented checklist per entity type; the system records who verified, when, and what evidence; payment status is never an input. | 3.4, 6.1 | M |
| REQ-026 | Sponsored articles and campaigns carry a visible "Sponsored" label on the page, listing cards and shares. | 6.3 | M |
| REQ-027 | Editors can create, schedule and publish articles (interview, feature, spotlight, opinion, announcement, news) with title, body, author, hero image, tags and SEO fields. | 5.3 | M |
| REQ-028 | Articles can be linked to profiles and industries; a profile page lists its related articles. | 5.3 | S |
| REQ-029 | A Stories page lists articles filterable by type and industry. | 12.2 | M |
| REQ-030 | A Nominate page collects: nominator identity, nominee identity, category, supporting evidence, and a conflict-of-interest declaration. | 7.4, 12.2 | M |
| REQ-031 | Staff can list, filter, export and set the status of nominations. | 7.4 | M |
| REQ-032 | The Awards section shows the edition, categories, eligibility criteria and the nomination/judging process, managed as content. | 5.4, 7.3 | M |
| REQ-033 | Category, nominee and winner pages exist and display only consented nominees. | 12.2 | L |
| REQ-034 | The system tracks each nomination through the 10 stages of the recognition process. | 7.4 | Q12 |
| REQ-035 | Judges have accounts and can score assigned nominations against published criteria. | 5.4 | Q12 |
| REQ-036 | Judges and staff declare conflicts of interest; conflicted judges cannot see or score that nomination. | 5.4 | Q12 |
| REQ-037 | Judge and scoring views never display subscription, payment or sponsorship status of nominees. | 5.4, 7.4 | Q12 |
| REQ-038 | Winner notification and public announcement can be prepared and published from the system. | 7.4 | L |
| REQ-039 | A Contact page sends messages to a staff inbox and logs them. | 12.2 | M |

## 2. Non-functional

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-040 | All public pages are responsive and usable at 360 px width. | 12.1 | M |
| REQ-041 | Profile and directory pages reach Largest Contentful Paint ≤ 2.5 s on a mid-range Android phone over throttled 4G. *(assumed target)* | 12 | M |
| REQ-042 | Images are served resized and in modern formats via a CDN. | 12.1 | M |
| REQ-043 | Public pages meet WCAG 2.1 AA. *(assumed target)* | 12 | M |
| REQ-044 | Public content is server-rendered or static so it is indexable; canonical URLs and sitemaps are provided. | 5.3 | M |
| REQ-045 | Production availability ≥ 99.5% monthly; the checkout path is monitored with alerting. *(assumed)* | — | M |
| REQ-046 | All traffic is HTTPS; OWASP Top-10 mitigations, security headers, form rate limits and dependency scanning are in place. | — | M |
| REQ-047 | Staff accounts require strong passwords and MFA. | — | M |
| REQ-048 | Card data never touches WHOISWHO servers (hosted checkout / tokenised). | — | M |
| REQ-049 | Database and uploads are backed up daily; a restore is tested before launch; RPO 24 h, RTO 8 h. *(assumed)* | — | M |
| REQ-050 | Errors, uptime and payment failures are monitored; logs contain no personal data beyond IDs. | — | M |
| REQ-051 | All staff/admin actions on profiles, payments and reviews are written to an immutable audit log. | 15.7 | M |
| REQ-052 | Brand colours, type and the tagline "Influence. Achievement. Legacy." are implemented as design tokens so the approved identity can be applied or changed without rebuilding pages. | 2.2, 12.1 | M |
| REQ-053 | The site is English-only, handles names with diacritics (UTF-8), uses NGN formatting and the WAT time zone. | 7.2 | M |
| REQ-054 | The design handles ≥ 50 concurrent users and ≥ 100k page views/month on the launch infrastructure. *(assumed)* | — | M |
| REQ-055 | Supports the latest two versions of Chrome, Safari, Edge, Firefox and Android Chrome. | — | M |
| REQ-056 | A staging environment mirrors production; automated tests run on every PR. | — | M |

## 3. Data & privacy

*Nigeria Data Protection Act 2023 (NDPA) is assumed to apply; exact duties must be confirmed by Legal (Q5). This is not legal advice.*

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-057 | For each category of person whose data is held (subscriber, nominator, nominee, stub subject, visitor) the lawful basis is recorded and approved by Legal before data is collected. | 13.2 | M |
| REQ-058 | A privacy notice is shown at every point of collection; consent (where used) is stored with timestamp and policy version. | 12.2 | M |
| REQ-059 | The system supports data-subject requests (access, correction, erasure, objection, portability) with intake, tracking and due dates. | — | M |
| REQ-060 | Profile owners choose which contact details are public; the default is none beyond the enquiry form. | 5.1 | M |
| REQ-061 | Marketing email requires separate opt-in and every message has one-click unsubscribe. | 10.5 | M |
| REQ-062 | Non-essential cookies/analytics load only after consent. | — | M |
| REQ-063 | A retention schedule exists per data class and a job deletes or anonymises data when it expires. | — | S |
| REQ-064 | Nominee personal data is kept private and is not shown publicly until the nominee has consented. | 7.4 | M |
| REQ-065 | A register lists every processor and its location (hosting, email, payments, analytics, any AI provider), each with a data-processing agreement. | — | M |
| REQ-066 | A breach-response procedure exists (detect, log, notify the regulator and affected people within the legal deadline). | — | M |
| REQ-067 | The system does not collect or store sensitive personal data (health, religion, politics, ethnicity, biometrics, criminal records, sexual life, trade-union membership) in profiles, stubs or nominations; the intake forms exclude these and free text is checked. | — | M |
| REQ-068 | Individuals under 18 cannot hold a profile. | — | M |
| REQ-069 | Access is role-based and least-privilege; bulk export of personal data is limited to named roles and logged. | — | M |
| REQ-070 | Only gateway reference IDs and amounts are stored for payments, never card numbers. | — | M |
| REQ-071 | A takedown/complaint process (defamation, privacy, IP) is published, tracked and has a response target. | 15.7 | M |
| REQ-072 | A data-protection impact assessment is completed, and a data-protection officer/contact designated, before any personal data about non-consenting individuals is published. | — | Q2 |

## 4. Integrations

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-073 | A payment gateway supporting NGN cards and bank transfer is integrated via hosted checkout; webhooks are signature-verified and idempotent. | 8 P1 | M |
| REQ-074 | Payments made by bank transfer/invoice can be confirmed by Finance in the admin and recorded against a subscription. | 9.3 | M |
| REQ-075 | Transactional email is sent via a provider with SPF, DKIM and DMARC configured; bounces are recorded. | — | M |
| REQ-076 | Receipts and invoices (PDF, with VAT where applicable) are generated for each payment. | 9.3 | M |
| REQ-077 | The subscription register, prospects and enquiries can be exported/imported (CSV) for CaromHQ; a live integration is built only if CaromHQ exposes an API. | 15.5 | Q16 |
| REQ-078 | Privacy-friendly web analytics measure visits, journey completion and conversions. | 14.3 | M |
| REQ-079 | Search is provided by the database or a hosted search service sized for ≥ 10k profiles. | 5.2 | M |
| REQ-080 | Uploaded media is stored in object storage behind a CDN. | 12.1 | M |
| REQ-081 | Public forms are protected by a bot challenge. | — | M |
| REQ-082 | Pages expose social share cards; social links are displayed as links only (no social-platform API use). | 10.5 | M |

## 5. Admin & ops

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-083 | An admin console with roles: Admin, Editor, Reviewer, Sales/Support, Finance, Judge. | 13 | M |
| REQ-084 | A review queue shows each pending item with a field-level diff, sources and approve / edit / reject-with-reason actions. | 8 P2 | M |
| REQ-085 | Staff can create or edit a profile on behalf of a subscriber or as a complimentary listing; these are flagged and attributed. | 9.3 | M |
| REQ-086 | Finance can refund, cancel, extend or comp a subscription; each action requires a reason and is audited. | — | M |
| REQ-087 | The subscription register exports to CSV with the counting-rule status of each row. | 9.3, 14 | M |
| REQ-088 | A weekly dashboard shows: paid subscriptions, revenue, average value, profiles submitted/published, journey completion rate, visitors, enquiries, nominations. | 14.2–14.5 | M |
| REQ-089 | A support inbox tracks enquiries, claims and complaints with owner, status and age. | 8 P2 | M |
| REQ-090 | Any visitor can report a profile; reports enter an abuse queue visible to staff. | 15.7 | M |
| REQ-091 | Staff can unpublish or hide any profile or article immediately. | 15.7 | M |
| REQ-092 | Staff can bulk-import profiles from CSV with validation and a dry-run, tagged with source and importer. | 8 P3 | M |
| REQ-093 | Prospect lists can be imported/exported (CSV) in a store separate from public data (see REQ-176). | 9.2 | S |
| REQ-094 | Email notifications fire for: payment received, profile received, approved, rejected with reason, renewal due, claim received, claim resolved. | 8 P2 | M |
| REQ-095 | An operations runbook documents deploy, rollback, backup restore and incident handling. | 15.5 | M |

## 6. Timeline-driven

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-096 | By **20 Nov 2026**: homepage, core landing pages and the agreed site structure are viewable (staging acceptable). | 8 P1 | M |
| REQ-097 | By **20 Nov 2026**: subscription packages are configured and payment + confirmation work end-to-end in the gateway's test mode. | 8 P1 | M |
| REQ-098 | By **20 Nov 2026**: individual, business and institutional profile templates and the directory structure exist. | 8 P1 | M |
| REQ-099 | By **21 Nov 2026**: live-mode payments and confirmation emails work, because direct outreach and "secure the first paid subscriptions" start that day. | 8 P2 | M |
| REQ-100 | By **21 Nov 2026**: Terms, Privacy Policy and subscription terms are published before any customer data is collected. | 12.2 | M |
| REQ-101 | By **20 Dec 2026**: initial subscribers are onboarded, their profiles reviewed and published, and the support process works. | 8 P2 | M |
| REQ-102 | By **20 Dec 2026**: weekly reporting (REQ-088) is live. | 14.6 | M |
| REQ-103 | By **31 Jan 2027**: the website and core subscription journey operate in production. | 8 P3 | M |
| REQ-104 | By **31 Jan 2027**: the directory is populated to the volume decided in Q1. | 8 P3 | Q1 |
| REQ-105 | By **31 Jan 2027**: ≥ 10 paid subscriptions (or NGN 5m) are onboarded and recorded under REQ-022. | 8 P3, 9 | M |
| REQ-106 | The nomination page (REQ-030) is live before nomination awareness campaigns begin (Phase 2, from 21 Nov). | 8 P2 | M |

## 7. [implied] but unstated

| ID | Requirement | Source | Pri |
|---|---|---|---|
| REQ-107 | Users can register with email verification. | — | M |
| REQ-108 | Users can log in, log out, reset passwords, and see/revoke sessions. | — | M |
| REQ-109 | Roles are enforced: visitor, profile owner, org editor, staff roles, judge. | — | M |
| REQ-110 | A user can deactivate or delete their account; the effect on their profile and subscription is defined and shown before confirming. | — | M |
| REQ-111 | An organisation account supports multiple users (one owner, several editors). | 6.2 | Q17 |
| REQ-112 | Money is stored in integer minor units in NGN; VAT treatment is configured by Finance. | — | M |
| REQ-113 | Failed or abandoned payments can be retried via a link; no profile is published without confirmed payment. | 9.3 | M |
| REQ-114 | Gateway webhooks are reconciled against the register daily; duplicates and mismatches are flagged. | — | M |
| REQ-115 | Refunds follow the policy decided in Q10 and are recorded against the original payment. | — | Q10 |
| REQ-116 | Each subscription has a term, expiry, renewal reminders and a defined state on expiry (e.g. downgrade, not delete). | — | Q10 |
| REQ-117 | Plan upgrades/downgrades follow a defined proration rule. | — | S |
| REQ-118 | Promo codes and complimentary grants can be created by Admin. | 9.3 | S |
| REQ-119 | Corporate customers can be invoiced and pay by bank transfer. | 6.2 | M |
| REQ-120 | Terms, Privacy, Cookie policy, Subscription terms and Awards rules are published. | 12.2, 13.2 | M |
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
| REQ-132 | Organisation stubs (approach A) can be created from approved sources and published as Unclaimed after review. | brief | M |
| REQ-133 | Individual stubs (approach C) are disabled by a feature flag until Legal sign-off (REQ-057, REQ-072) and the Q2 decision. | brief | Q2 |
| REQ-134 | Bulk-import adapters exist for CSV/JSON and for the Wikidata SPARQL API; other sources are entered manually. | brief | M |
| REQ-135 | Wikidata-sourced records store the QID and are re-synced on request. | brief | S |
| REQ-136 | A source registry records for each source: name, entity types, URL, access method (API / export / crawl / manual), licence and terms summary, robots.txt check date, approver, status. | brief | M |
| REQ-137 | Ingestion is permitted only from sources with status Approved; Approved requires a named person to have checked terms, licence and robots rules. | brief | M |
| REQ-138 | No automated collection from sites whose terms or robots rules forbid it, nor from login-walled sites or social platforms (including LinkedIn); any crawler identifies itself, rate-limits itself and never circumvents blocks. | brief | M |
| REQ-139 | Individual stubs hold only these fields: name, current title, current organisation, industry, public-record roles (board, office), published awards, official or organisational website. | brief | Q2 |
| REQ-140 | Never stored for individuals: home address, phone, personal email, family, date of birth or age, health, religion, politics, ethnicity, finances, allegations, or personal social handles. The schema offers no field for them. | brief | M |
| REQ-141 | Free-text fields in any stub or draft are scanned for patterns (phones, emails, addresses, ages, sensitive keywords) and blocked from review until cleared. | brief | M |
| REQ-142 | Every field on an acquired record stores: source URL, retrieval date, confidence (high / medium / low) and method (API / import / manual / AI). | brief | M |
| REQ-143 | Fields with low confidence or no source cannot be published. | brief | M |
| REQ-144 | Public stub pages show the source link for key facts and a "last reviewed" date. | brief | M |
| REQ-145 | Each organisation and individual has one persistent ID; matching uses RC number, Wikidata QID, and normalised name + organisation + location. | brief | M |
| REQ-146 | Probable duplicates are queued for human merge review; individuals are never auto-merged. | brief | M |
| REQ-147 | A suppression list holds people and organisations who asked for removal; imports skip them. | brief | M |
| REQ-148 | All acquired records go to the editorial review queue (REQ-084) with sources visible; nothing acquired is public without approval by a named reviewer who is not the importer. | brief | M |
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
| REQ-163 | Reviewers apply a neutral-content checklist: no allegations, litigation, rumours, opinion or personal life; only dated, sourced, professional facts. | brief | M |
| REQ-164 | A corrections log records every change made after publication on a stub, with the reason. | brief | M |
| REQ-165 | AI drafting is behind a feature flag, off by default (Q6). | brief | Q6 |
| REQ-166 | If enabled, AI drafting uses only text from approved-source records as input; every sentence maps to a stored source passage; sentences without a mapping are rejected automatically. | brief | Q6 |
| REQ-167 | AI output may not infer or characterise (e.g. "widely regarded", "renowned") and may not address sensitive attributes. | brief | Q6 |
| REQ-168 | AI drafts are internal only; a reviewer ticks each claim against its source before publishing; model, prompt, version and source hash are stored. | brief | Q6 |
| REQ-169 | The AI provider contract excludes training on submitted data and is in the processor register (REQ-065). | brief | Q6 |
| REQ-170 | The public disclosure page states whether AI assisted a profile (as Legal decides). | brief | Q6 |
| REQ-171 | A dashboard reports profiles by entity type, source, status and age. | brief | S |
| REQ-172 | A subject may add a short right-of-reply note to a profile or editorial item, subject to review. | brief | S |
| REQ-173 | Claimed profile owners can see which sources the platform used about them. | brief | S |
| REQ-174 | Disputes (claim, correction, removal) escalate to a named Editor-in-Chief role. | brief | M |
| REQ-175 | Complimentary individual profiles created by staff require recorded consent from the person before publication. | 9.3 | M |
| REQ-176 | Prospect data (approach B) lives in a separate store, is never public, and cannot become a profile except by entering the REQ-148 review path with an approved source. | brief | M |

## 9. Non-software work (tracked, not built)

| # | Work | Spec | Owner (per spec) |
|---|---|---|---|
| N1 | Brand positioning, identity, messaging and tone guidelines | 2, 11, 12, P1 | Venture CEO + Brand & Creative |
| N2 | Pricing and package definition (what each tier includes) | 6.1, P1 | Venture CEO |
| N3 | Sales: prospect research (approach B), 500→300→150→100→10 funnel, outreach scripts, proposals, introductions | 9, 10, P1–P3 | Venture CEO / sales |
| N4 | Prospecting tools: licensed lead data or manual research; verify tool terms of service | brief | Sales |
| N5 | Sales presentation, proposal documents | P1 | Sales |
| N6 | Launch communications, social channels, content calendar, email marketing | 10.5, P1–P2 | Marketing |
| N7 | Editorial content production and media relations | 5.3, 10.3, P2 | Editorial |
| N8 | Final award categories, eligibility criteria, judging panel, governance and conflict-of-interest policy | 5.4, 7.3–7.4 | Venture CEO |
| N9 | Legal: subscription terms, privacy and data protection, award rules, sponsorship agreements, NDPA basis for publishing personal data | 13.2 | Legal |
| N10 | Sponsorship packages and sponsor prospecting | 6.4, P3 | Venture CEO |
| N11 | Event concept, venue, budget, date, ticketing/tables, execution schedule | 5.5, 6.5, 7.5, P3 | Events |
| N12 | Partnerships (associations, chambers, alumni, media) | 10.4 | Venture CEO |
| N13 | Payment-gateway merchant account opening and KYC | P1 | Finance |
| N14 | CaromHQ record-keeping discipline | 15.5 | All |
| N15 | Reviewer recruitment and editorial guidelines for the review queue | brief | Editorial |
| N16 | Data-acquisition source approval (terms/robots/licence checks) | brief | Editorial + Legal |
| N17 | Next-quarter roadmap, Intelligence & Insights (5.6), expansion beyond Nigeria | 5.6, 18 | Venture CEO |
